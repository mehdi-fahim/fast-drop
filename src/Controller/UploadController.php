<?php

namespace App\Controller;

use App\Entity\File;
use App\Service\AuditService;
use App\Service\UploadService;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\File\UploadedFile;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\Validator\Constraints\File as FileConstraint;
use Symfony\Component\Validator\Validator\ValidatorInterface;

class UploadController extends AbstractController
{
    public function __construct(
        private UploadService $uploadService,
        private AuditService $auditService,
        private EntityManagerInterface $entityManager
    ) {}

    #[Route('/api/upload/start', name: 'api_upload_start', methods: ['POST'])]
    public function startUpload(Request $request): JsonResponse
    {
        $user = $this->getUser();
        if (!$user || !$user->canUpload()) {
            return new JsonResponse(['error' => 'Access denied'], 403);
        }

        $data = json_decode($request->getContent(), true);
        
        if (!$data || !isset($data['filename'], $data['size'])) {
            return new JsonResponse(['error' => 'Missing required fields'], 400);
        }

        try {
            $file = $this->uploadService->startUpload(
                $user,
                $data['filename'],
                (int)$data['size'],
                $data['description'] ?? null,
                $data['project_name'] ?? null,
                $data['recipients'] ?? null,
                isset($data['expires_at']) ? new \DateTimeImmutable($data['expires_at']) : null
            );

            return new JsonResponse([
                'success' => true,
                'file_id' => $file->getId(),
                'chunk_size' => $this->uploadService->getChunkSize(),
            ]);
        } catch (\Exception $e) {
            return new JsonResponse(['error' => $e->getMessage()], 400);
        }
    }

    #[Route('/api/upload/chunk', name: 'api_upload_chunk', methods: ['POST'])]
    public function uploadChunk(Request $request): JsonResponse
    {
        $user = $this->getUser();
        if (!$user || !$user->canUpload()) {
            return new JsonResponse(['error' => 'Access denied'], 403);
        }

        $fileId = $request->request->get('file_id');
        $chunkIndex = $request->request->get('chunk_index');
        // chunk_data is sent as a Blob in FormData → available under $request->files
        $uploadedChunk = $request->files->get('chunk_data');

        if ($fileId === null || $chunkIndex === null || $uploadedChunk === null) {
            return new JsonResponse(['error' => 'Missing required fields'], 400);
        }

        $chunkIndex = (int)$chunkIndex;
        $chunkData = file_get_contents($uploadedChunk->getPathname());

        $file = $this->entityManager->getRepository(File::class)->find($fileId);
        if (!$file || $file->getOwner() !== $user) {
            return new JsonResponse(['error' => 'File not found'], 404);
        }

        try {
            $success = $this->uploadService->uploadChunk($file, $chunkIndex, $chunkData);
            
            if ($success) {
                $progress = $this->uploadService->getUploadProgress($file);
                return new JsonResponse([
                    'success' => true,
                    'progress' => $progress,
                ]);
            } else {
                return new JsonResponse(['error' => 'Failed to upload chunk'], 500);
            }
        } catch (\Exception $e) {
            return new JsonResponse(['error' => $e->getMessage()], 500);
        }
    }

    #[Route('/api/upload/complete', name: 'api_upload_complete', methods: ['POST'])]
    public function completeUpload(Request $request): JsonResponse
    {
        $user = $this->getUser();
        if (!$user || !$user->canUpload()) {
            return new JsonResponse(['error' => 'Access denied'], 403);
        }

        $data = json_decode($request->getContent(), true);
        
        if (!$data || !isset($data['file_id'], $data['checksum'])) {
            return new JsonResponse(['error' => 'Missing required fields'], 400);
        }

        $file = $this->entityManager->getRepository(File::class)->find($data['file_id']);
        if (!$file || $file->getOwner() !== $user) {
            return new JsonResponse(['error' => 'File not found'], 404);
        }

        try {
            $success = $this->uploadService->completeUpload($file, $data['checksum']);
            
            if ($success) {
                return new JsonResponse([
                    'success' => true,
                    'file_id' => $file->getId(),
                    'filename' => $file->getFilename(),
                    'size' => $file->getSizeBytes(),
                    'checksum' => $file->getChecksum(),
                ]);
            } else {
                return new JsonResponse(['error' => 'Failed to complete upload'], 500);
            }
        } catch (\Exception $e) {
            return new JsonResponse(['error' => $e->getMessage()], 500);
        }
    }

    #[Route('/api/upload/progress/{fileId}', name: 'api_upload_progress', methods: ['GET'])]
    public function getUploadProgress(int $fileId): JsonResponse
    {
        $user = $this->getUser();
        if (!$user || !$user->canUpload()) {
            return new JsonResponse(['error' => 'Access denied'], 403);
        }

        $file = $this->entityManager->getRepository(File::class)->find($fileId);
        if (!$file || $file->getOwner() !== $user) {
            return new JsonResponse(['error' => 'File not found'], 404);
        }

        $progress = $this->uploadService->getUploadProgress($file);
        
        return new JsonResponse([
            'success' => true,
            'progress' => $progress,
        ]);
    }

    #[Route('/api/upload/cancel/{fileId}', name: 'api_upload_cancel', methods: ['POST'])]
    public function cancelUpload(int $fileId): JsonResponse
    {
        $user = $this->getUser();
        if (!$user || !$user->canUpload()) {
            return new JsonResponse(['error' => 'Access denied'], 403);
        }

        $file = $this->entityManager->getRepository(File::class)->find($fileId);
        if (!$file || $file->getOwner() !== $user) {
            return new JsonResponse(['error' => 'File not found'], 404);
        }

        try {
            $success = $this->uploadService->cancelUpload($file);
            
            if ($success) {
                return new JsonResponse(['success' => true]);
            } else {
                return new JsonResponse(['error' => 'Failed to cancel upload'], 500);
            }
        } catch (\Exception $e) {
            return new JsonResponse(['error' => $e->getMessage()], 500);
        }
    }

    #[Route('/upload/direct', name: 'upload_direct', methods: ['POST'])]
    public function directUpload(Request $request, ValidatorInterface $validator): Response
    {
        $user = $this->getUser();
        if (!$user || !$user->canUpload()) {
            throw $this->createAccessDeniedException('You do not have permission to upload files.');
        }

        $uploadedFile = $request->files->get('file');
        if (!$uploadedFile instanceof UploadedFile) {
            $this->addFlash('error', 'No file uploaded');
            return $this->redirectToRoute('upload');
        }

        // Validate file
        // Accept any file type; enforce only size to avoid false negatives on uncommon MIME types (e.g., .md)
        $constraint = new FileConstraint([
            'maxSize' => '10G',
        ]);

        $violations = $validator->validate($uploadedFile, $constraint);
        if (count($violations) > 0) {
            $this->addFlash('error', 'Invalid file type or size');
            return $this->redirectToRoute('upload');
        }

        try {
            $file = $this->uploadService->uploadFile(
                $user,
                $uploadedFile,
                $request->request->get('description'),
                $request->request->get('project_name'),
                $request->request->get('recipients') ? explode(',', $request->request->get('recipients')) : null,
                $request->request->get('expires_at') ? new \DateTimeImmutable($request->request->get('expires_at')) : null
            );

            $this->addFlash('success', 'File uploaded successfully');
            return $this->redirectToRoute('files_list');
        } catch (\Exception $e) {
            $this->addFlash('error', 'Upload failed: ' . $e->getMessage());
            return $this->redirectToRoute('upload');
        }
    }
}
