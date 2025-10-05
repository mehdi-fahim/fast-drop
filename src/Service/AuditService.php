<?php

namespace App\Service;

use App\Entity\AuditLog;
use App\Entity\File;
use App\Entity\User;
use Doctrine\ORM\EntityManagerInterface;
use Psr\Log\LoggerInterface;
use Symfony\Component\HttpFoundation\RequestStack;

class AuditService
{
    // Audit action constants
    public const ACTION_LOGIN = 'login';
    public const ACTION_LOGOUT = 'logout';
    public const ACTION_FILE_UPLOAD = 'file_upload';
    public const ACTION_FILE_DOWNLOAD = 'file_download';
    public const ACTION_TOKEN_CREATE = 'token_create';
    public const ACTION_TOKEN_REVOKE = 'token_revoke';
    public const ACTION_FILE_DELETE = 'file_delete';
    public const ACTION_USER_CREATE = 'user_create';
    public const ACTION_USER_UPDATE = 'user_update';
    public const ACTION_USER_DELETE = 'user_delete';

    private EntityManagerInterface $entityManager;
    private RequestStack $requestStack;
    private LoggerInterface $logger;

    public function __construct(
        EntityManagerInterface $entityManager,
        RequestStack $requestStack,
        LoggerInterface $logger
    ) {
        $this->entityManager = $entityManager;
        $this->requestStack = $requestStack;
        $this->logger = $logger;
    }

    public function log(
        string $action,
        ?User $user = null,
        ?File $file = null,
        ?array $metadata = null,
        ?string $resourceId = null,
        ?string $resourceType = null
    ): void {
        $request = $this->requestStack->getCurrentRequest();
        
        $auditLog = new AuditLog();
        $auditLog->setAction($action);
        $auditLog->setUser($user);
        $auditLog->setFile($file);
        $auditLog->setMetadata($metadata ?? []);
        $auditLog->setResourceId($resourceId);
        $auditLog->setResourceType($resourceType);

        if ($request) {
            $auditLog->setIp($this->getClientIp($request));
            $auditLog->setUserAgent($request->headers->get('User-Agent'));
        }

        $this->entityManager->persist($auditLog);
        $this->entityManager->flush();

        // Also log to application logger
        $this->logger->info('Audit log created', [
            'action' => $action,
            'user_id' => $user?->getId(),
            'file_id' => $file?->getId(),
            'ip' => $auditLog->getIp(),
            'metadata' => $metadata
        ]);
    }

    public function logUpload(User $user, File $file, array $metadata = []): void
    {
        $this->log(
            AuditLog::ACTION_UPLOAD,
            $user,
            $file,
            array_merge($metadata, [
                'fileSize' => $file->getSizeBytes(),
                'filename' => $file->getFilename(),
                'checksum' => $file->getChecksum()
            ])
        );
    }

    public function logDownload(?User $user, File $file, ?string $tokenId = null, array $metadata = []): void
    {
        $downloadMetadata = array_merge($metadata, [
            'fileSize' => $file->getSizeBytes(),
            'filename' => $file->getFilename()
        ]);

        if ($tokenId) {
            $downloadMetadata['token_id'] = $tokenId;
        }

        $this->log(
            AuditLog::ACTION_DOWNLOAD,
            $user,
            $file,
            $downloadMetadata
        );
    }

    public function logDelete(User $user, File $file, array $metadata = []): void
    {
        $this->log(
            AuditLog::ACTION_DELETE,
            $user,
            $file,
            array_merge($metadata, [
                'fileSize' => $file->getSizeBytes(),
                'filename' => $file->getFilename()
            ])
        );
    }

    public function logTokenGeneration(User $user, File $file, string $tokenId, array $metadata = []): void
    {
        $this->log(
            AuditLog::ACTION_GENERATE_TOKEN,
            $user,
            $file,
            array_merge($metadata, [
                'token_id' => $tokenId,
                'filename' => $file->getFilename()
            ]),
            $tokenId,
            'download_token'
        );
    }

    public function logTokenRevocation(User $user, File $file, string $tokenId, array $metadata = []): void
    {
        $this->log(
            AuditLog::ACTION_REVOKE_TOKEN,
            $user,
            $file,
            array_merge($metadata, [
                'token_id' => $tokenId,
                'filename' => $file->getFilename()
            ]),
            $tokenId,
            'download_token'
        );
    }

    public function logLogin(User $user, bool $success = true, array $metadata = []): void
    {
        $this->log(
            AuditLog::ACTION_LOGIN,
            $user,
            null,
            array_merge($metadata, [
                'success' => $success
            ])
        );
    }

    public function logLogout(User $user, array $metadata = []): void
    {
        $this->log(
            AuditLog::ACTION_LOGOUT,
            $user,
            null,
            $metadata
        );
    }

    public function logQuarantine(User $user, File $file, string $reason, array $metadata = []): void
    {
        $this->log(
            AuditLog::ACTION_QUARANTINE,
            $user,
            $file,
            array_merge($metadata, [
                'reason' => $reason,
                'filename' => $file->getFilename(),
                'fileSize' => $file->getSizeBytes()
            ])
        );
    }

    public function logReleaseQuarantine(User $user, File $file, array $metadata = []): void
    {
        $this->log(
            AuditLog::ACTION_RELEASE_QUARANTINE,
            $user,
            $file,
            array_merge($metadata, [
                'filename' => $file->getFilename(),
                'fileSize' => $file->getSizeBytes()
            ])
        );
    }

    public function logUserCreation(User $creator, User $newUser, array $metadata = []): void
    {
        $this->log(
            AuditLog::ACTION_CREATE_USER,
            $creator,
            null,
            array_merge($metadata, [
                'new_user_id' => $newUser->getId(),
                'new_user_email' => $newUser->getEmail(),
                'new_user_roles' => $newUser->getRoles()
            ]),
            (string)$newUser->getId(),
            'user'
        );
    }

    public function logUserUpdate(User $updater, User $updatedUser, array $changes = [], array $metadata = []): void
    {
        $this->log(
            AuditLog::ACTION_UPDATE_USER,
            $updater,
            null,
            array_merge($metadata, [
                'updated_user_id' => $updatedUser->getId(),
                'updated_user_email' => $updatedUser->getEmail(),
                'changes' => $changes
            ]),
            (string)$updatedUser->getId(),
            'user'
        );
    }

    public function logUserDeletion(User $deleter, User $deletedUser, array $metadata = []): void
    {
        $this->log(
            AuditLog::ACTION_DELETE_USER,
            $deleter,
            null,
            array_merge($metadata, [
                'deleted_user_id' => $deletedUser->getId(),
                'deleted_user_email' => $deletedUser->getEmail(),
                'deleted_user_roles' => $deletedUser->getRoles()
            ]),
            (string)$deletedUser->getId(),
            'user'
        );
    }

    private function getClientIp($request): string
    {
        // Check for forwarded IP headers (behind proxy/load balancer)
        $forwardedFor = $request->headers->get('X-Forwarded-For');
        if ($forwardedFor) {
            $ips = explode(',', $forwardedFor);
            return trim($ips[0]);
        }

        $realIp = $request->headers->get('X-Real-IP');
        if ($realIp) {
            return $realIp;
        }

        return $request->getClientIp() ?? 'unknown';
    }

    public function getRecentActivity(int $limit = 50): array
    {
        $qb = $this->entityManager->createQueryBuilder();
        
        return $qb->select('al')
            ->from(AuditLog::class, 'al')
            ->leftJoin('al.user', 'u')
            ->leftJoin('al.file', 'f')
            ->orderBy('al.timestamp', 'DESC')
            ->setMaxResults($limit)
            ->getQuery()
            ->getResult();
    }

    public function getActivityStats(int $days = 30): array
    {
        $qb = $this->entityManager->createQueryBuilder();
        $startDate = new \DateTimeImmutable("-{$days} days");

        return $qb->select([
                'al.action',
                'COUNT(al.id) as actionCount',
                'DATE(al.timestamp) as date'
            ])
            ->from(AuditLog::class, 'al')
            ->where('al.timestamp >= :startDate')
            ->setParameter('startDate', $startDate)
            ->groupBy('al.action, DATE(al.timestamp)')
            ->orderBy('date', 'DESC')
            ->getQuery()
            ->getResult();
    }

    public function getDownloadStats(int $days = 30): array
    {
        $qb = $this->entityManager->createQueryBuilder();
        $startDate = new \DateTimeImmutable("-{$days} days");

        return $qb->select([
                'COUNT(al.id) as totalDownloads',
                'SUM(CASE WHEN al.metadata IS NOT NULL AND JSON_EXTRACT(al.metadata, "$.fileSize") IS NOT NULL THEN JSON_EXTRACT(al.metadata, "$.fileSize") ELSE 0 END) as totalSize',
                'DATE(al.timestamp) as date'
            ])
            ->from(AuditLog::class, 'al')
            ->where('al.action = :download')
            ->andWhere('al.timestamp >= :startDate')
            ->setParameter('download', AuditLog::ACTION_DOWNLOAD)
            ->setParameter('startDate', $startDate)
            ->groupBy('DATE(al.timestamp)')
            ->orderBy('date', 'DESC')
            ->getQuery()
            ->getResult();
    }

    public function cleanupOldLogs(int $daysToKeep = 365): int
    {
        $qb = $this->entityManager->createQueryBuilder();
        $cutoffDate = new \DateTimeImmutable("-{$daysToKeep} days");

        $deleted = $qb->delete(AuditLog::class, 'al')
            ->where('al.timestamp < :cutoffDate')
            ->setParameter('cutoffDate', $cutoffDate)
            ->getQuery()
            ->execute();

        if ($deleted > 0) {
            $this->logger->info('Old audit logs cleaned up', ['deleted_count' => $deleted]);
        }

        return $deleted;
    }
}
