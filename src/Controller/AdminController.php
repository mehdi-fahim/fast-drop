<?php

namespace App\Controller;

use App\Entity\File;
use App\Entity\User;
use App\Repository\AuditLogRepository;
use App\Repository\DownloadTokenRepository;
use App\Repository\FileRepository;
use App\Repository\UserRepository;
use App\Service\AuditService;
use App\Service\TokenService;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\Validator\Validator\ValidatorInterface;

class AdminController extends AbstractController
{
    public function __construct(
        private UserRepository $userRepository,
        private FileRepository $fileRepository,
        private DownloadTokenRepository $tokenRepository,
        private AuditLogRepository $auditLogRepository,
        private TokenService $tokenService,
        private AuditService $auditService,
        private EntityManagerInterface $entityManager,
        private UserPasswordHasherInterface $passwordHasher
    ) {}

    #[Route('/admin', name: 'admin_dashboard')]
    public function dashboard(): Response
    {
        $this->denyAccessUnlessGranted('ROLE_ADMIN');

        // Get basic statistics
        $totalUsers = $this->userRepository->count([]);
        $totalFiles = $this->fileRepository->count([]);
        $totalTokens = $this->tokenRepository->count([]);
        
        // Calculate total storage size from files table
        $qb = $this->entityManager->createQueryBuilder();
        $qb->select('SUM(f.sizeBytes) as totalSize')
           ->from(File::class, 'f')
           ->where('f.status != :deleted')
           ->setParameter('deleted', File::STATUS_DELETED);
        $result = $qb->getQuery()->getSingleScalarResult();
        $totalSize = $result ? (int)$result : 0;
        
        // Calculate total quota used from users (should match totalSize)
        $qbUsers = $this->entityManager->createQueryBuilder();
        $qbUsers->select('SUM(u.quotaUsedBytes) as totalUsed, SUM(u.quotaTotalBytes) as totalQuota')
            ->from(User::class, 'u');
        $userQuotaResult = $qbUsers->getQuery()->getSingleResult();
        $totalUsedByUsers = $userQuotaResult['totalUsed'] ? (int)$userQuotaResult['totalUsed'] : 0;
        $totalQuotaAllocated = $userQuotaResult['totalQuota'] ? (int)$userQuotaResult['totalQuota'] : 0;
        
        $userStats = ['total_users' => $totalUsers];
        $fileStats = [
            'total_files' => $totalFiles,
            'total_size' => $totalSize,
            'total_used_by_users' => $totalUsedByUsers,
            'total_quota_allocated' => $totalQuotaAllocated,
        ];
        $tokenStats = ['active_tokens' => $totalTokens];
        
        // Get recent activity (simplified for now)
        $recentActivity = [];
        
        // Get files expiring soon (simplified for now)
        $expiringFiles = [];
        $expiringTokens = [];
        $quarantinedFiles = [];

        return $this->render('admin/dashboard.html.twig', [
            'user_stats' => $userStats,
            'file_stats' => $fileStats,
            'token_stats' => $tokenStats,
            'recent_activity' => $recentActivity,
            'expiring_files' => $expiringFiles,
            'expiring_tokens' => $expiringTokens,
            'quarantined_files' => $quarantinedFiles,
        ]);
    }

    #[Route('/admin/users', name: 'admin_users')]
    public function users(Request $request): Response
    {
        $this->denyAccessUnlessGranted('ROLE_ADMIN');

        $users = $this->userRepository->findAll();
        $storageStats = $this->userRepository->getStorageStatsByUser();

        return $this->render('admin/users.html.twig', [
            'users' => $users,
            'storage_stats' => $storageStats,
        ]);
    }

    #[Route('/admin/users/create', name: 'admin_user_create', methods: ['GET', 'POST'])]
    public function createUser(Request $request): Response
    {
        $this->denyAccessUnlessGranted('ROLE_ADMIN');

        if ($request->isMethod('POST')) {
            $email = $request->request->get('email');
            $password = $request->request->get('password');
            $roles = $request->request->all('roles');
            $quotaBytes = $request->request->get('quota_bytes');
            $status = $request->request->get('status', 'active');
            $expiresAt = $request->request->get('expires_at');
            $notes = $request->request->get('notes');

            // Validate input
            if (empty($email) || empty($password)) {
                $this->addFlash('error', 'Email and password are required');
                return $this->redirectToRoute('admin_user_create');
            }

            // Check if user already exists
            if ($this->userRepository->findByEmail($email)) {
                $this->addFlash('error', 'User with this email already exists');
                return $this->redirectToRoute('admin_user_create');
            }

            // Create user
            $user = new User();
            $user->setEmail($email);
            $user->setPassword($this->passwordHasher->hashPassword($user, $password));
            $user->setRoles($roles);
            $user->setQuotaTotalBytes($quotaBytes ? (int)$quotaBytes : null);
            $user->setQuotaUsedBytes(0);
            $user->setStatus($status);
            $user->setExpiresAt($expiresAt ? new \DateTimeImmutable($expiresAt) : null);
            $user->setNotes($notes);

            $this->entityManager->persist($user);
            $this->entityManager->flush();

            // Log user creation
            $this->auditService->logUserCreation($this->getUser(), $user);

            $this->addFlash('success', 'User created successfully');
            return $this->redirectToRoute('admin_users');
        }

        return $this->render('admin/user_form.html.twig', [
            'user' => null,
            'roles' => [User::ROLE_USER, User::ROLE_UPLOADER, User::ROLE_VIEWER, User::ROLE_ADMIN],
        ]);
    }

    #[Route('/admin/users/{id}/edit', name: 'admin_user_edit', methods: ['GET', 'POST'])]
    public function editUser(int $id, Request $request): Response
    {
        $this->denyAccessUnlessGranted('ROLE_ADMIN');

        $user = $this->userRepository->find($id);
        if (!$user) {
            throw $this->createNotFoundException('User not found');
        }

        if ($request->isMethod('POST')) {
            $email = $request->request->get('email');
            $password = $request->request->get('password');
            $roles = $request->request->all('roles');
            $quotaBytes = $request->request->get('quota_bytes');
            $status = $request->request->get('status', 'active');
            $expiresAt = $request->request->get('expires_at');
            $notes = $request->request->get('notes');

            $changes = [];

            if ($email !== $user->getEmail()) {
                $changes['email'] = ['old' => $user->getEmail(), 'new' => $email];
                $user->setEmail($email);
            }

            if (!empty($password)) {
                $user->setPassword($this->passwordHasher->hashPassword($user, $password));
                $changes['password'] = ['changed' => true];
            }

            if ($roles !== $user->getRoles()) {
                $changes['roles'] = ['old' => $user->getRoles(), 'new' => $roles];
                $user->setRoles($roles);
            }

            if ($quotaBytes !== (string)$user->getQuotaTotalBytes()) {
                $changes['quota'] = ['old' => $user->getQuotaTotalBytes(), 'new' => $quotaBytes ? (int)$quotaBytes : null];
                $user->setQuotaTotalBytes($quotaBytes ? (int)$quotaBytes : null);
            }

            if ($status !== $user->getStatus()) {
                $changes['status'] = ['old' => $user->getStatus(), 'new' => $status];
                $user->setStatus($status);
            }

            $newExpiresAt = $expiresAt ? new \DateTimeImmutable($expiresAt) : null;
            if ($newExpiresAt != $user->getExpiresAt()) {
                $changes['expires_at'] = ['old' => $user->getExpiresAt()?->format('Y-m-d'), 'new' => $expiresAt];
                $user->setExpiresAt($newExpiresAt);
            }

            if ($notes !== $user->getNotes()) {
                $changes['notes'] = ['old' => $user->getNotes(), 'new' => $notes];
                $user->setNotes($notes);
            }

            $this->entityManager->flush();

            // Log user update
            if (!empty($changes)) {
                $this->auditService->logUserUpdate($this->getUser(), $user, $changes);
            }

            $this->addFlash('success', 'User updated successfully');
            return $this->redirectToRoute('admin_users');
        }

        return $this->render('admin/user_form.html.twig', [
            'user' => $user,
            'roles' => [User::ROLE_USER, User::ROLE_UPLOADER, User::ROLE_VIEWER, User::ROLE_ADMIN],
        ]);
    }

    #[Route('/admin/files', name: 'admin_files')]
    public function files(Request $request): Response
    {
        $this->denyAccessUnlessGranted('ROLE_ADMIN');

        $status = $request->query->get('status');
        $ownerId = $request->query->get('owner');

        $qb = $this->entityManager->createQueryBuilder();
        $qb->select('f')
            ->from(File::class, 'f')
            ->leftJoin('f.owner', 'u')
            ->orderBy('f.createdAt', 'DESC');

        if ($status) {
            $qb->andWhere('f.status = :status')
                ->setParameter('status', $status);
        }

        if ($ownerId) {
            $qb->andWhere('f.owner = :owner')
                ->setParameter('owner', $ownerId);
        }

        $files = $qb->getQuery()->getResult();

        return $this->render('admin/files.html.twig', [
            'files' => $files,
            'current_status' => $status,
            'current_owner' => $ownerId,
        ]);
    }

    #[Route('/admin/files/{id}/status', name: 'admin_file_update_status', methods: ['POST'])]
    public function updateFileStatus(int $id, Request $request): Response
    {
        $this->denyAccessUnlessGranted('ROLE_ADMIN');

        $file = $this->fileRepository->find($id);
        if (!$file) {
            throw $this->createNotFoundException('File not found');
        }

        $newStatus = $request->request->get('status');
        $allowed = [
            File::STATUS_OK,
            File::STATUS_QUARANTINE,
            File::STATUS_UPLOADING,
            File::STATUS_DELETED,
        ];

        if (!in_array($newStatus, $allowed, true)) {
            $this->addFlash('error', 'Statut invalide');
            return $this->redirectToRoute('admin_files');
        }

        $oldStatus = $file->getStatus();
        if ($oldStatus !== $newStatus) {
            $file->setStatus($newStatus);
            $this->entityManager->flush();

            // Audit
            $this->auditService->logFileUpdate($this->getUser(), $file, [
                'status' => ['old' => $oldStatus, 'new' => $newStatus],
            ]);

            $this->addFlash('success', 'Statut mis à jour');
        }

        return $this->redirectToRoute('admin_files');
    }

    #[Route('/admin/storage', name: 'admin_storage')]
    public function storage(Request $request): Response
    {
        $this->denyAccessUnlessGranted('ROLE_ADMIN');

        // Get all users with their storage stats
        $users = $this->userRepository->findAll();
        
        // Calculate global stats
        $qbGlobal = $this->entityManager->createQueryBuilder();
        $qbGlobal->select('SUM(u.quotaUsedBytes) as totalUsed, SUM(u.quotaTotalBytes) as totalAllocated')
            ->from(User::class, 'u');
        $globalStats = $qbGlobal->getQuery()->getSingleResult();
        
        $totalUsed = $globalStats['totalUsed'] ? (int)$globalStats['totalUsed'] : 0;
        $totalAllocated = $globalStats['totalAllocated'] ? (int)$globalStats['totalAllocated'] : 0;
        
        // Calculate total files size
        $qbFiles = $this->entityManager->createQueryBuilder();
        $qbFiles->select('SUM(f.sizeBytes) as totalSize')
            ->from(File::class, 'f')
            ->where('f.status != :deleted')
            ->setParameter('deleted', File::STATUS_DELETED);
        $filesResult = $qbFiles->getQuery()->getSingleScalarResult();
        $totalFilesSize = $filesResult ? (int)$filesResult : 0;

        return $this->render('admin/storage.html.twig', [
            'users' => $users,
            'total_used' => $totalUsed,
            'total_allocated' => $totalAllocated,
            'total_files_size' => $totalFilesSize,
        ]);
    }

    #[Route('/admin/users/{id}/quota', name: 'admin_user_update_quota', methods: ['POST'])]
    public function updateUserQuota(int $id, Request $request): Response
    {
        $this->denyAccessUnlessGranted('ROLE_ADMIN');

        $user = $this->userRepository->find($id);
        if (!$user) {
            throw $this->createNotFoundException('User not found');
        }

        $quotaGb = $request->request->get('quota_gb');
        
        // Convert GB to bytes (null means unlimited)
        $quotaBytes = null;
        if ($quotaGb !== '' && $quotaGb !== null) {
            $quotaBytes = (int)((float)$quotaGb * 1024 * 1024 * 1024);
        }

        $oldQuota = $user->getQuotaTotalBytes();
        if ($oldQuota !== $quotaBytes) {
            $user->setQuotaTotalBytes($quotaBytes);
            $this->entityManager->flush();

            // Audit
            $this->auditService->logUserUpdate($this->getUser(), $user, [
                'quota' => [
                    'old' => $oldQuota ? round($oldQuota / 1024 / 1024 / 1024, 2) . ' GB' : 'Illimité',
                    'new' => $quotaBytes ? round($quotaBytes / 1024 / 1024 / 1024, 2) . ' GB' : 'Illimité',
                ],
            ]);

            $this->addFlash('success', 'Quota mis à jour');
        }

        return $this->redirectToRoute('admin_storage');
    }

    #[Route('/admin/storage/recalculate', name: 'admin_storage_recalculate', methods: ['POST'])]
    public function recalculateQuotas(): Response
    {
        $this->denyAccessUnlessGranted('ROLE_ADMIN');

        $users = $this->userRepository->findAll();
        $updated = 0;

        foreach ($users as $user) {
            // Calculate real used space from files
            $qb = $this->entityManager->createQueryBuilder();
            $qb->select('SUM(f.sizeBytes) as totalSize')
               ->from(File::class, 'f')
               ->where('f.owner = :user')
               ->andWhere('f.status != :deleted')
               ->setParameter('user', $user)
               ->setParameter('deleted', File::STATUS_DELETED);
            
            $result = $qb->getQuery()->getSingleScalarResult();
            $actualUsed = $result ? (int)$result : 0;

            if ($user->getQuotaUsedBytes() !== $actualUsed) {
                $user->setQuotaUsedBytes($actualUsed);
                $updated++;
            }
        }

        $this->entityManager->flush();

        $this->addFlash('success', "Quotas recalculés ! {$updated} utilisateurs mis à jour.");
        return $this->redirectToRoute('admin_storage');
    }

    #[Route('/admin/files/{id}/delete', name: 'admin_file_delete', methods: ['POST'])]
    public function deleteFile(int $id): Response
    {
        $this->denyAccessUnlessGranted('ROLE_ADMIN');

        $file = $this->fileRepository->find($id);
        if (!$file) {
            throw $this->createNotFoundException('File not found');
        }

        // Log deletion
        $this->auditService->logDelete($this->getUser(), $file);

        // Update user quota (ensure it doesn't go negative)
        $owner = $file->getOwner();
        $newQuotaUsed = max(0, $owner->getQuotaUsedBytes() - $file->getSizeBytes());
        $owner->setQuotaUsedBytes($newQuotaUsed);
        
        // Delete file entity (cascade will handle related entities)
        $this->entityManager->remove($file);
        $this->entityManager->flush();

        $this->addFlash('success', 'File deleted successfully');
        return $this->redirectToRoute('admin_files');
    }

    #[Route('/admin/tokens', name: 'admin_tokens')]
    public function tokens(Request $request): Response
    {
        $this->denyAccessUnlessGranted('ROLE_ADMIN');

        $status = $request->query->get('status');
        
        if ($status === 'active') {
            $tokens = $this->tokenRepository->findActiveTokens();
        } elseif ($status === 'expired') {
            $tokens = $this->tokenRepository->findExpiredTokens();
        } elseif ($status === 'exhausted') {
            $tokens = $this->tokenRepository->findExhaustedTokens();
        } else {
            $tokens = $this->tokenRepository->findAll();
        }

        return $this->render('admin/tokens.html.twig', [
            'tokens' => $tokens,
            'current_status' => $status,
        ]);
    }

    #[Route('/admin/tokens/{id}/revoke', name: 'admin_token_revoke', methods: ['POST'])]
    public function revokeToken(int $id): Response
    {
        $this->denyAccessUnlessGranted('ROLE_ADMIN');

        $token = $this->tokenRepository->find($id);
        if (!$token) {
            throw $this->createNotFoundException('Token not found');
        }

        $this->tokenService->revokeToken($token);

        $this->addFlash('success', 'Token revoked successfully');
        return $this->redirectToRoute('admin_tokens');
    }

    #[Route('/admin/audit', name: 'admin_audit')]
    public function audit(Request $request): Response
    {
        $this->denyAccessUnlessGranted('ROLE_ADMIN');

        $action = $request->query->get('action');
        $userId = $request->query->get('user');
        $days = (int)($request->query->get('days', 30));

        $qb = $this->entityManager->createQueryBuilder();
        $qb->select('al')
            ->from('App\Entity\AuditLog', 'al')
            ->leftJoin('al.user', 'u')
            ->leftJoin('al.file', 'f')
            ->orderBy('al.timestamp', 'DESC')
            ->setMaxResults(100);

        if ($action) {
            $qb->andWhere('al.action = :action')
                ->setParameter('action', $action);
        }

        if ($userId) {
            $qb->andWhere('al.user = :user')
                ->setParameter('user', $userId);
        }

        $logs = $qb->getQuery()->getResult();

        return $this->render('admin/audit.html.twig', [
            'logs' => $logs,
            'current_action' => $action,
            'current_user' => $userId,
            'days' => $days,
        ]);
    }

    #[Route('/admin/settings', name: 'admin_settings')]
    public function settings(): Response
    {
        $this->denyAccessUnlessGranted('ROLE_ADMIN');

        return $this->render('admin/settings.html.twig');
    }
}
