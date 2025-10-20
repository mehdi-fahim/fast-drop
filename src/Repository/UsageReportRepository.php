<?php

namespace App\Repository;

use App\Entity\UsageReport;
use App\Entity\User;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<UsageReport>
 */
class UsageReportRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, UsageReport::class);
    }

    /**
     * Get or create a usage report for a user on a specific date
     */
    public function getOrCreateReport(User $user, \DateTimeInterface $date): UsageReport
    {
        $report = $this->findOneBy([
            'user' => $user,
            'reportDate' => $date
        ]);

        if (!$report) {
            $report = new UsageReport();
            $report->setUser($user);
            $report->setReportDate($date);
            
            $this->getEntityManager()->persist($report);
            $this->getEntityManager()->flush();
        }

        return $report;
    }

    /**
     * Get usage statistics for a user over a date range
     */
    public function getUserStats(User $user, \DateTimeInterface $startDate, \DateTimeInterface $endDate): array
    {
        $qb = $this->createQueryBuilder('ur')
            ->where('ur.user = :user')
            ->andWhere('ur.reportDate BETWEEN :startDate AND :endDate')
            ->setParameter('user', $user)
            ->setParameter('startDate', $startDate)
            ->setParameter('endDate', $endDate);

        $reports = $qb->getQuery()->getResult();

        $stats = [
            'total_uploads' => 0,
            'total_downloads' => 0,
            'total_uploads_size' => 0,
            'total_downloads_size' => 0,
            'total_files_shared' => 0,
            'total_files_expired' => 0,
            'total_files_deleted' => 0,
            'file_types' => [],
            'hourly_activity' => array_fill(0, 24, 0),
            'daily_activity' => []
        ];

        foreach ($reports as $report) {
            $stats['total_uploads'] += $report->getUploadsCount();
            $stats['total_downloads'] += $report->getDownloadsCount();
            $stats['total_uploads_size'] += $report->getUploadsSizeBytes();
            $stats['total_downloads_size'] += $report->getDownloadsSizeBytes();
            $stats['total_files_shared'] += $report->getFilesSharedCount();
            $stats['total_files_expired'] += $report->getFilesExpiredCount();
            $stats['total_files_deleted'] += $report->getFilesDeletedCount();

            // Merge file types
            foreach ($report->getFileTypes() as $type => $count) {
                if (!isset($stats['file_types'][$type])) {
                    $stats['file_types'][$type] = 0;
                }
                $stats['file_types'][$type] += $count;
            }

            // Merge hourly activity
            foreach ($report->getHourlyActivity() as $hour => $activity) {
                $stats['hourly_activity'][$hour] += $activity;
            }

            // Daily activity
            $stats['daily_activity'][$report->getReportDate()->format('Y-m-d')] = [
                'uploads' => $report->getUploadsCount(),
                'downloads' => $report->getDownloadsCount(),
                'uploads_size' => $report->getUploadsSizeBytes(),
                'downloads_size' => $report->getDownloadsSizeBytes(),
                'total_activity' => $report->getTotalActivity()
            ];
        }

        // Sort file types by count
        arsort($stats['file_types']);

        return $stats;
    }

    /**
     * Get global statistics for all users over a date range
     */
    public function getGlobalStats(\DateTimeInterface $startDate, \DateTimeInterface $endDate): array
    {
        $qb = $this->createQueryBuilder('ur')
            ->where('ur.reportDate BETWEEN :startDate AND :endDate')
            ->setParameter('startDate', $startDate)
            ->setParameter('endDate', $endDate);

        $reports = $qb->getQuery()->getResult();

        $stats = [
            'total_users' => 0,
            'total_uploads' => 0,
            'total_downloads' => 0,
            'total_uploads_size' => 0,
            'total_downloads_size' => 0,
            'total_files_shared' => 0,
            'total_files_expired' => 0,
            'total_files_deleted' => 0,
            'file_types' => [],
            'hourly_activity' => array_fill(0, 24, 0),
            'daily_activity' => [],
            'top_users' => []
        ];

        $userStats = [];

        foreach ($reports as $report) {
            $userId = $report->getUser()->getId();
            
            if (!isset($userStats[$userId])) {
                $userStats[$userId] = [
                    'user' => $report->getUser(),
                    'uploads' => 0,
                    'downloads' => 0,
                    'uploads_size' => 0,
                    'downloads_size' => 0,
                    'files_shared' => 0,
                    'files_expired' => 0,
                    'files_deleted' => 0,
                    'total_activity' => 0
                ];
            }

            $userStats[$userId]['uploads'] += $report->getUploadsCount();
            $userStats[$userId]['downloads'] += $report->getDownloadsCount();
            $userStats[$userId]['uploads_size'] += $report->getUploadsSizeBytes();
            $userStats[$userId]['downloads_size'] += $report->getDownloadsSizeBytes();
            $userStats[$userId]['files_shared'] += $report->getFilesSharedCount();
            $userStats[$userId]['files_expired'] += $report->getFilesExpiredCount();
            $userStats[$userId]['files_deleted'] += $report->getFilesDeletedCount();
            $userStats[$userId]['total_activity'] += $report->getTotalActivity();

            $stats['total_uploads'] += $report->getUploadsCount();
            $stats['total_downloads'] += $report->getDownloadsCount();
            $stats['total_uploads_size'] += $report->getUploadsSizeBytes();
            $stats['total_downloads_size'] += $report->getDownloadsSizeBytes();
            $stats['total_files_shared'] += $report->getFilesSharedCount();
            $stats['total_files_expired'] += $report->getFilesExpiredCount();
            $stats['total_files_deleted'] += $report->getFilesDeletedCount();

            // Merge file types
            foreach ($report->getFileTypes() as $type => $count) {
                if (!isset($stats['file_types'][$type])) {
                    $stats['file_types'][$type] = 0;
                }
                $stats['file_types'][$type] += $count;
            }

            // Merge hourly activity
            foreach ($report->getHourlyActivity() as $hour => $activity) {
                $stats['hourly_activity'][$hour] += $activity;
            }

            // Daily activity
            $dateKey = $report->getReportDate()->format('Y-m-d');
            if (!isset($stats['daily_activity'][$dateKey])) {
                $stats['daily_activity'][$dateKey] = [
                    'uploads' => 0,
                    'downloads' => 0,
                    'uploads_size' => 0,
                    'downloads_size' => 0,
                    'total_activity' => 0
                ];
            }
            $stats['daily_activity'][$dateKey]['uploads'] += $report->getUploadsCount();
            $stats['daily_activity'][$dateKey]['downloads'] += $report->getDownloadsCount();
            $stats['daily_activity'][$dateKey]['uploads_size'] += $report->getUploadsSizeBytes();
            $stats['daily_activity'][$dateKey]['downloads_size'] += $report->getDownloadsSizeBytes();
            $stats['daily_activity'][$dateKey]['total_activity'] += $report->getTotalActivity();
        }

        $stats['total_users'] = count($userStats);

        // Sort file types by count
        arsort($stats['file_types']);

        // Get top users by total activity
        uasort($userStats, function($a, $b) {
            return $b['total_activity'] <=> $a['total_activity'];
        });
        $stats['top_users'] = array_slice($userStats, 0, 10, true);

        return $stats;
    }

    /**
     * Get usage trends over time for a user
     */
    public function getUserTrends(User $user, int $days = 30): array
    {
        $endDate = new \DateTime();
        $startDate = (clone $endDate)->modify("-{$days} days");

        $qb = $this->createQueryBuilder('ur')
            ->where('ur.user = :user')
            ->andWhere('ur.reportDate BETWEEN :startDate AND :endDate')
            ->orderBy('ur.reportDate', 'ASC')
            ->setParameter('user', $user)
            ->setParameter('startDate', $startDate)
            ->setParameter('endDate', $endDate);

        $reports = $qb->getQuery()->getResult();

        $trends = [];
        $currentDate = clone $startDate;

        while ($currentDate <= $endDate) {
            $dateKey = $currentDate->format('Y-m-d');
            $trends[$dateKey] = [
                'date' => $dateKey,
                'uploads' => 0,
                'downloads' => 0,
                'uploads_size' => 0,
                'downloads_size' => 0,
                'total_activity' => 0
            ];
            $currentDate->modify('+1 day');
        }

        foreach ($reports as $report) {
            $dateKey = $report->getReportDate()->format('Y-m-d');
            if (isset($trends[$dateKey])) {
                $trends[$dateKey]['uploads'] = $report->getUploadsCount();
                $trends[$dateKey]['downloads'] = $report->getDownloadsCount();
                $trends[$dateKey]['uploads_size'] = $report->getUploadsSizeBytes();
                $trends[$dateKey]['downloads_size'] = $report->getDownloadsSizeBytes();
                $trends[$dateKey]['total_activity'] = $report->getTotalActivity();
            }
        }

        return array_values($trends);
    }

    /**
     * Clean up old usage reports (keep only last 365 days)
     */
    public function cleanupOldReports(): int
    {
        $cutoffDate = new \DateTime('-365 days');
        
        $qb = $this->createQueryBuilder('ur')
            ->delete()
            ->where('ur.reportDate < :cutoffDate')
            ->setParameter('cutoffDate', $cutoffDate);

        return $qb->getQuery()->execute();
    }
}
