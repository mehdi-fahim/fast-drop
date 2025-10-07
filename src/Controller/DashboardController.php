<?php

namespace App\Controller;

use App\Entity\File;
use App\Repository\FileRepository;
use App\Repository\UserRepository;
use App\Service\AuditService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;

class DashboardController extends AbstractController
{
    public function __construct(
        private FileRepository $fileRepository,
        private UserRepository $userRepository,
        private AuditService $auditService
    ) {}

    #[Route('/', name: 'home')]
    #[Route('/dashboard', name: 'dashboard')]
    public function dashboard(): Response
    {
        $user = $this->getUser();
        if (!$user) {
            return $this->redirectToRoute('login');
        }

        // Log dashboard access
        $this->auditService->log(AuditService::ACTION_LOGIN, $user);

        // Get user's files
        $userFiles = $this->fileRepository->findFilesByOwner($user);

        // Get recent activity
        $recentActivity = $this->auditService->getRecentActivity(10);

        // Get storage stats
        $storageStats = $this->fileRepository->getStorageStats();

        return $this->render('dashboard/index.html.twig', [
            'user' => $user,
            'files' => $userFiles,
            'recent_activity' => $recentActivity,
            'storage_stats' => $storageStats,
            'quota_usage_percentage' => $user->getQuotaUsagePercentage(),
        ]);
    }

    #[Route('/upload', name: 'upload')]
    public function upload(): Response
    {
        $user = $this->getUser();
        if (!$user || !$user->canUpload()) {
            throw $this->createAccessDeniedException('You do not have permission to upload files.');
        }

        return $this->render('upload/index.html.twig', [
            'user' => $user,
            'chunk_size' => 5 * 1024 * 1024, // 5MB
            'max_file_size' => $user->getQuotaTotalBytes() - $user->getQuotaUsedBytes(),
        ]);
    }


    #[Route('/profile', name: 'profile')]
    public function profile(): Response
    {
        $user = $this->getUser();
        if (!$user) {
            return $this->redirectToRoute('login');
        }

        return $this->render('dashboard/profile.html.twig', [
            'user' => $user,
        ]);
    }
}
