<?php

namespace App\Repository;

use App\Entity\AuditLog;
use App\Entity\File;
use App\Entity\User;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<AuditLog>
 *
 * @method AuditLog|null find($id, $lockMode = null, $lockVersion = null)
 * @method AuditLog|null findOneBy(array $criteria, array $orderBy = null)
 * @method AuditLog[]    findAll()
 * @method AuditLog[]    findBy(array $criteria, array $orderBy = null, $limit = null, $offset = null)
 */
class AuditLogRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, AuditLog::class);
    }

    public function findRecentLogs(int $limit = 100): array
    {
        return $this->createQueryBuilder('al')
            ->orderBy('al.timestamp', 'DESC')
            ->setMaxResults($limit)
            ->getQuery()
            ->getResult();
    }

    public function findLogsByUser(User $user): array
    {
        return $this->createQueryBuilder('al')
            ->where('al.user = :user')
            ->setParameter('user', $user)
            ->orderBy('al.timestamp', 'DESC')
            ->getQuery()
            ->getResult();
    }

    public function findLogsByFile(File $file): array
    {
        return $this->createQueryBuilder('al')
            ->where('al.file = :file')
            ->setParameter('file', $file)
            ->orderBy('al.timestamp', 'DESC')
            ->getQuery()
            ->getResult();
    }

    public function findLogsByAction(string $action): array
    {
        return $this->createQueryBuilder('al')
            ->where('al.action = :action')
            ->setParameter('action', $action)
            ->orderBy('al.timestamp', 'DESC')
            ->getQuery()
            ->getResult();
    }

    public function findLogsByDateRange(\DateTimeImmutable $start, \DateTimeImmutable $end): array
    {
        return $this->createQueryBuilder('al')
            ->where('al.timestamp BETWEEN :start AND :end')
            ->setParameter('start', $start)
            ->setParameter('end', $end)
            ->orderBy('al.timestamp', 'DESC')
            ->getQuery()
            ->getResult();
    }

    public function findLogsByIp(string $ip): array
    {
        return $this->createQueryBuilder('al')
            ->where('al.ip = :ip')
            ->setParameter('ip', $ip)
            ->orderBy('al.timestamp', 'DESC')
            ->getQuery()
            ->getResult();
    }

    public function getActivityStats(): array
    {
        return $this->createQueryBuilder('al')
            ->select([
                'al.action',
                'COUNT(al.id) as actionCount',
                'DATE(al.timestamp) as date'
            ])
            ->groupBy('al.action, DATE(al.timestamp)')
            ->orderBy('date', 'DESC')
            ->getQuery()
            ->getResult();
    }

    public function getActivityStatsByUser(): array
    {
        return $this->createQueryBuilder('al')
            ->select([
                'u.email',
                'COUNT(al.id) as actionCount',
                'COUNT(DISTINCT al.action) as uniqueActions'
            ])
            ->leftJoin('al.user', 'u')
            ->groupBy('al.user')
            ->orderBy('actionCount', 'DESC')
            ->getQuery()
            ->getResult();
    }

    public function getDownloadStats(): array
    {
        return $this->createQueryBuilder('al')
            ->select([
                'COUNT(al.id) as totalDownloads',
                'DATE(al.timestamp) as date'
            ])
            ->where('al.action = :download')
            ->setParameter('download', AuditLog::ACTION_DOWNLOAD)
            ->groupBy('DATE(al.timestamp)')
            ->orderBy('date', 'DESC')
            ->setMaxResults(30)
            ->getQuery()
            ->getResult();
    }

    public function getUploadStats(): array
    {
        return $this->createQueryBuilder('al')
            ->select([
                'COUNT(al.id) as totalUploads',
                'SUM(CASE WHEN al.metadata IS NOT NULL AND JSON_EXTRACT(al.metadata, "$.fileSize") IS NOT NULL THEN JSON_EXTRACT(al.metadata, "$.fileSize") ELSE 0 END) as totalSize',
                'DATE(al.timestamp) as date'
            ])
            ->where('al.action = :upload')
            ->setParameter('upload', AuditLog::ACTION_UPLOAD)
            ->groupBy('DATE(al.timestamp)')
            ->orderBy('date', 'DESC')
            ->setMaxResults(30)
            ->getQuery()
            ->getResult();
    }

    public function findSuspiciousActivity(): array
    {
        // Find multiple failed login attempts from same IP
        return $this->createQueryBuilder('al')
            ->select([
                'al.ip',
                'COUNT(al.id) as attemptCount'
            ])
            ->where('al.action = :action')
            ->andWhere('al.metadata LIKE :failed')
            ->setParameter('action', AuditLog::ACTION_LOGIN)
            ->setParameter('failed', '%"success":false%')
            ->groupBy('al.ip')
            ->having('COUNT(al.id) >= :threshold')
            ->setParameter('threshold', 5)
            ->orderBy('attemptCount', 'DESC')
            ->getQuery()
            ->getResult();
    }

    public function cleanupOldLogs(int $daysToKeep = 365): int
    {
        $cutoffDate = (new \DateTimeImmutable())->modify("-{$daysToKeep} days");

        return $this->createQueryBuilder('al')
            ->delete()
            ->where('al.timestamp < :cutoff')
            ->setParameter('cutoff', $cutoffDate)
            ->getQuery()
            ->execute();
    }
}
