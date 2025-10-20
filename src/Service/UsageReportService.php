<?php

namespace App\Service;

use App\Entity\UsageReport;
use App\Entity\User;
use App\Entity\File;
use App\Entity\AuditLog;
use App\Repository\UsageReportRepository;
use Doctrine\ORM\EntityManagerInterface;
use Psr\Log\LoggerInterface;

class UsageReportService
{
    public function __construct(
        private EntityManagerInterface $entityManager,
        private UsageReportRepository $usageReportRepository,
        private LoggerInterface $logger
    ) {}

    /**
     * Record an upload event
     */
    public function recordUpload(User $user, File $file): void
    {
        try {
            $report = $this->usageReportRepository->getOrCreateReport($user, new \DateTime());
            
            $report->incrementUploadsCount()
                   ->addUploadsSize($file->getSizeBytes());
            
            // Record file type
            $extension = pathinfo($file->getOriginalFilename(), PATHINFO_EXTENSION);
            if ($extension) {
                $report->addFileType(strtolower($extension));
            }
            
            // Record hourly activity
            $report->incrementHourlyActivity((int) date('H'));
            
            $this->entityManager->flush();
            
            $this->logger->info('Recorded upload for user {user_id}: {filename}', [
                'user_id' => $user->getId(),
                'filename' => $file->getOriginalFilename(),
                'size' => $file->getSizeBytes()
            ]);
            
        } catch (\Exception $e) {
            $this->logger->error('Failed to record upload: {error}', [
                'error' => $e->getMessage(),
                'user_id' => $user->getId(),
                'file_id' => $file->getId()
            ]);
        }
    }

    /**
     * Record a download event
     */
    public function recordDownload(User $user, File $file): void
    {
        try {
            $report = $this->usageReportRepository->getOrCreateReport($user, new \DateTime());
            
            $report->incrementDownloadsCount()
                   ->addDownloadsSize($file->getSizeBytes());
            
            // Record hourly activity
            $report->incrementHourlyActivity((int) date('H'));
            
            $this->entityManager->flush();
            
            $this->logger->info('Recorded download for user {user_id}: {filename}', [
                'user_id' => $user->getId(),
                'filename' => $file->getOriginalFilename(),
                'size' => $file->getSizeBytes()
            ]);
            
        } catch (\Exception $e) {
            $this->logger->error('Failed to record download: {error}', [
                'error' => $e->getMessage(),
                'user_id' => $user->getId(),
                'file_id' => $file->getId()
            ]);
        }
    }

    /**
     * Record a file sharing event
     */
    public function recordFileShared(User $user, File $file): void
    {
        try {
            $report = $this->usageReportRepository->getOrCreateReport($user, new \DateTime());
            
            $report->incrementFilesSharedCount();
            
            $this->entityManager->flush();
            
            $this->logger->info('Recorded file sharing for user {user_id}: {filename}', [
                'user_id' => $user->getId(),
                'filename' => $file->getOriginalFilename()
            ]);
            
        } catch (\Exception $e) {
            $this->logger->error('Failed to record file sharing: {error}', [
                'error' => $e->getMessage(),
                'user_id' => $user->getId(),
                'file_id' => $file->getId()
            ]);
        }
    }

    /**
     * Record a file expiration event
     */
    public function recordFileExpired(User $user, File $file): void
    {
        try {
            $report = $this->usageReportRepository->getOrCreateReport($user, new \DateTime());
            
            $report->incrementFilesExpiredCount();
            
            $this->entityManager->flush();
            
            $this->logger->info('Recorded file expiration for user {user_id}: {filename}', [
                'user_id' => $user->getId(),
                'filename' => $file->getOriginalFilename()
            ]);
            
        } catch (\Exception $e) {
            $this->logger->error('Failed to record file expiration: {error}', [
                'error' => $e->getMessage(),
                'user_id' => $user->getId(),
                'file_id' => $file->getId()
            ]);
        }
    }

    /**
     * Record a file deletion event
     */
    public function recordFileDeleted(User $user, File $file): void
    {
        try {
            $report = $this->usageReportRepository->getOrCreateReport($user, new \DateTime());
            
            $report->incrementFilesDeletedCount();
            
            $this->entityManager->flush();
            
            $this->logger->info('Recorded file deletion for user {user_id}: {filename}', [
                'user_id' => $user->getId(),
                'filename' => $file->getOriginalFilename()
            ]);
            
        } catch (\Exception $e) {
            $this->logger->error('Failed to record file deletion: {error}', [
                'error' => $e->getMessage(),
                'user_id' => $user->getId(),
                'file_id' => $file->getId()
            ]);
        }
    }

    /**
     * Get user statistics for a date range
     */
    public function getUserStats(User $user, \DateTimeInterface $startDate, \DateTimeInterface $endDate): array
    {
        return $this->usageReportRepository->getUserStats($user, $startDate, $endDate);
    }

    /**
     * Get global statistics for a date range
     */
    public function getGlobalStats(\DateTimeInterface $startDate, \DateTimeInterface $endDate): array
    {
        return $this->usageReportRepository->getGlobalStats($startDate, $endDate);
    }

    /**
     * Get user trends over time
     */
    public function getUserTrends(User $user, int $days = 30): array
    {
        return $this->usageReportRepository->getUserTrends($user, $days);
    }

    /**
     * Generate daily reports for all active users
     */
    public function generateDailyReports(): int
    {
        $yesterday = new \DateTime('yesterday');
        
        // Get all users who had activity yesterday
        $qb = $this->entityManager->createQueryBuilder();
        $qb->select('DISTINCT u')
           ->from('App\Entity\User', 'u')
           ->join('App\Entity\File', 'f', 'WITH', 'f.user = u')
           ->where('DATE(f.createdAt) = :date')
           ->setParameter('date', $yesterday->format('Y-m-d'));
        
        $users = $qb->getQuery()->getResult();
        
        $reportsGenerated = 0;
        
        foreach ($users as $user) {
            try {
                // Get or create report for yesterday
                $report = $this->usageReportRepository->getOrCreateReport($user, $yesterday);
                
                // Get files created yesterday by this user
                $files = $this->entityManager->getRepository(File::class)
                    ->findBy([
                        'user' => $user,
                        'createdAt' => [
                            $yesterday->setTime(0, 0, 0),
                            $yesterday->setTime(23, 59, 59)
                        ]
                    ]);
                
                // Update report with file data
                foreach ($files as $file) {
                    $report->incrementUploadsCount()
                           ->addUploadsSize($file->getSizeBytes());
                    
                    $extension = pathinfo($file->getOriginalFilename(), PATHINFO_EXTENSION);
                    if ($extension) {
                        $report->addFileType(strtolower($extension));
                    }
                }
                
                $this->entityManager->flush();
                $reportsGenerated++;
                
            } catch (\Exception $e) {
                $this->logger->error('Failed to generate daily report for user {user_id}: {error}', [
                    'user_id' => $user->getId(),
                    'error' => $e->getMessage()
                ]);
            }
        }
        
        $this->logger->info('Generated {count} daily usage reports for {date}', [
            'count' => $reportsGenerated,
            'date' => $yesterday->format('Y-m-d')
        ]);
        
        return $reportsGenerated;
    }

    /**
     * Clean up old usage reports
     */
    public function cleanupOldReports(): int
    {
        $deletedCount = $this->usageReportRepository->cleanupOldReports();
        
        $this->logger->info('Cleaned up {count} old usage reports', [
            'count' => $deletedCount
        ]);
        
        return $deletedCount;
    }

    /**
     * Get formatted statistics for display
     */
    public function getFormattedStats(array $stats): array
    {
        return [
            'total_uploads' => number_format($stats['total_uploads']),
            'total_downloads' => number_format($stats['total_downloads']),
            'total_uploads_size' => $this->formatBytes($stats['total_uploads_size']),
            'total_downloads_size' => $this->formatBytes($stats['total_downloads_size']),
            'total_files_shared' => number_format($stats['total_files_shared']),
            'total_files_expired' => number_format($stats['total_files_expired']),
            'total_files_deleted' => number_format($stats['total_files_deleted']),
            'file_types' => $stats['file_types'],
            'hourly_activity' => $stats['hourly_activity'],
            'daily_activity' => $stats['daily_activity'],
            'top_users' => $stats['top_users'] ?? []
        ];
    }

    private function formatBytes(int $bytes): string
    {
        $units = ['B', 'KB', 'MB', 'GB', 'TB'];
        $bytes = max($bytes, 0);
        $pow = floor(($bytes ? log($bytes) : 0) / log(1024));
        $pow = min($pow, count($units) - 1);
        
        $bytes /= (1 << (10 * $pow));
        
        return round($bytes, 2) . ' ' . $units[$pow];
    }
}
