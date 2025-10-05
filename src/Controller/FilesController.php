<?php

namespace App\Controller;

use App\Repository\FileRepository;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;

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
}
