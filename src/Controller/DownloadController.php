<?php

namespace App\Controller;

use App\Entity\File;
use App\Service\AuditService;
use App\Service\StorageService;
use App\Service\TokenService;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\BinaryFileResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\ResponseHeaderBag;
use Symfony\Component\HttpFoundation\StreamedResponse;
use Symfony\Component\RateLimiter\RateLimiterFactory;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\DependencyInjection\Attribute\Autowire;

class DownloadController extends AbstractController
{
    public function __construct(
        private EntityManagerInterface $entityManager,
        private TokenService $tokenService,
        private StorageService $storageService,
        private AuditService $auditService
    ) {}

    #[Route('/download/{token}', name: 'download_with_token', methods: ['GET', 'POST'])]
    public function downloadWithToken(string $token, Request $request): Response
    {
        $clientIp = $request->getClientIp();
        
        // Rate limiting (temporarily disabled)
        // TODO: Re-enable when rate limiter is properly configured
        // $limiter = $this->downloadLimiterFactory->create($clientIp);
        // if (!$limiter->consume()->isAccepted()) {
        //     return new Response('Too many requests', 429);
        // }

        // Get password from request
        $password = $request->request->get('password') ?? $request->query->get('password');
        
        // Verify token
        $downloadToken = $this->tokenService->verifyToken($token, $clientIp, $password);
        
        if (!$downloadToken) {
            // Show password form if token exists but password is required
            if ($request->isMethod('POST') && $password === null) {
                $this->addFlash('error', 'Password is required for this download');
            } elseif ($request->isMethod('POST')) {
                $this->addFlash('error', 'Invalid token or password');
            }
            
            return $this->render('download/password_form.html.twig', [
                'token' => $token,
                'error' => $request->isMethod('POST') ? 'Invalid token or password' : null,
            ]);
        }

        $file = $downloadToken->getFile();
        
        // Check if file is accessible
        if (!$file->isAccessible()) {
            $this->addFlash('error', 'File is not available for download');
            return $this->render('download/error.html.twig', [
                'error' => 'File is not available for download',
                'reason' => $file->isExpired() ? 'File has expired' : 'File is not accessible'
            ]);
        }

        // Use the token (increment download count)
        if (!$this->tokenService->useToken($downloadToken)) {
            $this->addFlash('error', 'Download limit reached for this token');
            return $this->render('download/error.html.twig', [
                'error' => 'Download limit reached',
                'reason' => 'This token has reached its maximum download limit'
            ]);
        }

        // Log download
        $this->auditService->logDownload(
            null, // No authenticated user for token downloads
            $file,
            (string)$downloadToken->getId(),
            [
                'token_id' => $downloadToken->getId(),
                'downloads_remaining' => $downloadToken->getRemainingDownloads(),
                'client_ip' => $clientIp,
            ]
        );

        // Serve the file
        return $this->serveFile($file);
    }

    #[Route('/api/download/{id}', name: 'api_download_file', methods: ['GET'])]
    public function downloadFile(int $id, Request $request): Response
    {
        $user = $this->getUser();
        if (!$user || !$user->canView()) {
            return new Response('Access denied', 403);
        }

        $file = $this->entityManager->getRepository(File::class)->find($id);
        if (!$file) {
            return new Response('File not found', 404);
        }

        // Check if user owns the file or is admin
        if ($file->getOwner() !== $user && !$user->isAdmin()) {
            return new Response('Access denied', 403);
        }

        // Check if file is accessible
        if (!$file->isAccessible()) {
            return new Response('File is not accessible', 403);
        }

        // Log download
        $this->auditService->logDownload($user, $file, null, [
            'client_ip' => $request->getClientIp(),
        ]);

        // Serve the file
        return $this->serveFile($file);
    }

    #[Route('/api/download/info/{token}', name: 'api_download_info', methods: ['GET'])]
    public function getDownloadInfo(string $token, Request $request): Response
    {
        $clientIp = $request->getClientIp();
        $downloadToken = $this->tokenService->verifyToken($token, $clientIp);
        
        if (!$downloadToken) {
            return new Response('Invalid token', 404);
        }

        $file = $downloadToken->getFile();
        
        return $this->json([
            'filename' => $file->getFilename(),
            'size' => $file->getSizeBytes(),
            'formatted_size' => $file->getFormattedSize(),
            'description' => $file->getDescription(),
            'project_name' => $file->getProjectName(),
            'expires_at' => $file->getExpiresAt()?->format('Y-m-d H:i:s'),
            'downloads_remaining' => $downloadToken->getRemainingDownloads(),
            'requires_password' => $downloadToken->getPasswordHash() !== null,
            'is_accessible' => $file->isAccessible(),
        ]);
    }

    private function serveFile(File $file): Response
    {
        try {
            $storagePath = $file->getStoragePath();
            
            if (!$this->storageService->exists($storagePath)) {
                throw new \RuntimeException('File not found in storage');
            }

            $mimeType = $this->storageService->getMimeType($storagePath);
            $fileSize = $this->storageService->getSize($storagePath);

            // For large files, use streaming
            if ($fileSize > 100 * 1024 * 1024) { // 100MB
                return $this->streamFile($storagePath, $file->getFilename(), $mimeType);
            }

            // For smaller files, use binary response
            $content = $this->storageService->read($storagePath);
            
            $response = new Response($content);
            $response->headers->set('Content-Type', $mimeType);
            $response->headers->set('Content-Length', (string)$fileSize);
            $response->headers->set('Content-Disposition', 
                ResponseHeaderBag::DISPOSITION_ATTACHMENT . '; filename="' . $file->getFilename() . '"'
            );

            return $response;
        } catch (\Exception $e) {
            throw new \RuntimeException('Failed to serve file: ' . $e->getMessage());
        }
    }

    private function streamFile(string $storagePath, string $filename, string $mimeType): StreamedResponse
    {
        $response = new StreamedResponse();
        $response->headers->set('Content-Type', $mimeType);
        $response->headers->set('Content-Disposition', 
            ResponseHeaderBag::DISPOSITION_ATTACHMENT . '; filename="' . $filename . '"'
        );

        $response->setCallback(function () use ($storagePath) {
            $stream = $this->storageService->readStream($storagePath);
            
            if ($stream === false) {
                throw new \RuntimeException('Failed to open file stream');
            }

            // Stream the file in chunks
            while (!feof($stream)) {
                echo fread($stream, 8192); // 8KB chunks
                flush();
            }
            
            fclose($stream);
        });

        return $response;
    }
}
