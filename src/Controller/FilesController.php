<?php

namespace App\Controller;

use App\Repository\FileRepository;
use App\Service\TokenService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;

class FilesController extends AbstractController
{
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

        $expiresAt = new \DateTimeImmutable('+7 days');
        $maxDownloads = (int)($request->request->get('max_downloads') ?? 1);
        $password = $request->request->get('password') ?: null;

        $result = $tokenService->generateTokenWithPlain($file, $user, $expiresAt, $maxDownloads, $password);
        $plainToken = $result['token'];

        $shareUrl = $this->generateUrl('download_with_token', ['token' => $plainToken], UrlGeneratorInterface::ABSOLUTE_URL);
        $this->addFlash('success', 'Lien de téléchargement généré: ' . $shareUrl);

        return $this->redirectToRoute('files_list');
    }
}
