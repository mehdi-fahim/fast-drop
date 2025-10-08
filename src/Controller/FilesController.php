<?php

namespace App\Controller;

use App\Entity\File;
use App\Repository\FileRepository;
use App\Service\AuditService;
use App\Service\TokenService;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;

class FilesController extends AbstractController
{
    public function __construct(
        private EntityManagerInterface $entityManager,
        private AuditService $auditService
    ) {}
    #[Route('/files', name: 'files_list')]
    public function index(Request $request, FileRepository $fileRepository): Response
    {
        $user = $this->getUser();
        if (!$user) {
            return $this->redirectToRoute('login');
        }

        // Get user's files
        $files = $fileRepository->findBy(['owner' => $user], ['createdAt' => 'DESC']);

        return $this->render('files/index.html.twig', [
            'files' => $files,
            'user' => $user,
        ]);
    }

    #[Route('/files/{id}/token', name: 'files_generate_token', methods: ['POST'])]
    public function generateToken(int $id, Request $request, FileRepository $fileRepository, TokenService $tokenService): Response
    {
        $user = $this->getUser();
        if (!$user) {
            return $this->redirectToRoute('login');
        }

        $file = $fileRepository->find($id);
        if (!$file || $file->getOwner() !== $user) {
            $this->addFlash('error', "Fichier introuvable");
            return $this->redirectToRoute('files_list');
        }

        // Vérifier si le fichier a expiré
        if ($file->getExpiresAt() && $file->getExpiresAt() < new \DateTimeImmutable()) {
            $this->addFlash('error', "Impossible de créer un lien pour un fichier expiré");
            return $this->redirectToRoute('files_list');
        }

        $expiresAt = new \DateTimeImmutable('+7 days');
        $maxDownloads = (int)($request->request->get('max_downloads') ?? 1);
        $password = $request->request->get('password') ?: null;

        $result = $tokenService->generateTokenWithPlain($file, $user, $expiresAt, $maxDownloads, $password);
        $plainToken = $result['token'];

        $shareUrl = $this->generateUrl('download_with_token', ['token' => $plainToken], UrlGeneratorInterface::ABSOLUTE_URL);
        $this->addFlash('success', 'Lien de téléchargement généré: ' . $shareUrl);

        return $this->redirectToRoute('files_list');
    }

    #[Route('/files/{id}/expiration', name: 'files_edit_expiration', methods: ['POST'])]
    public function editExpiration(int $id, Request $request, FileRepository $fileRepository): Response
    {
        $user = $this->getUser();
        if (!$user) {
            return $this->redirectToRoute('login');
        }

        $file = $fileRepository->find($id);
        if (!$file || $file->getOwner() !== $user) {
            $this->addFlash('error', "Fichier introuvable");
            return $this->redirectToRoute('files_list');
        }

        $expirationDate = $request->request->get('expiration_date');
        
        if (empty($expirationDate)) {
            // Aucune expiration
            $file->setExpiresAt(null);
            $this->addFlash('success', 'Expiration supprimée - le fichier n\'expirera jamais');
        } else {
            $newExpiration = new \DateTimeImmutable($expirationDate . ' 23:59:59');
            
            // Vérifier que la date est dans le futur
            if ($newExpiration <= new \DateTimeImmutable()) {
                $this->addFlash('error', 'La date d\'expiration doit être dans le futur');
                return $this->redirectToRoute('files_list');
            }
            
            $file->setExpiresAt($newExpiration);
            $this->addFlash('success', 'Date d\'expiration modifiée au ' . $newExpiration->format('d/m/Y'));
        }

        $this->entityManager->flush();

        // Log de l'audit
        $this->auditService->logFileUpdate($user, $file, [
            'action' => 'expiration_updated',
            'new_expiration' => $file->getExpiresAt()?->format('Y-m-d H:i:s')
        ]);

        return $this->redirectToRoute('files_list');
    }
}
