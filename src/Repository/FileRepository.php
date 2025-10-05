<?php

namespace App\Repository;

use App\Entity\File;
use App\Entity\User;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<File>
 *
 * @method File|null find($id, $lockMode = null, $lockVersion = null)
 * @method File|null findOneBy(array $criteria, array $orderBy = null)
 * @method File[]    findAll()
 * @method File[]    findBy(array $criteria, array $orderBy = null, $limit = null, $offset = null)
 */
class FileRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, File::class);
    }

    public function findExpiredFiles(): array
    {
        return $this->createQueryBuilder('f')
            ->where('f.expiresAt IS NOT NULL')
            ->andWhere('f.expiresAt < :now')
            ->andWhere('f.status != :deleted')
            ->setParameter('now', new \DateTimeImmutable())
            ->setParameter('deleted', File::STATUS_DELETED)
            ->getQuery()
            ->getResult();
    }

    public function findFilesByOwner(User $owner): array
    {
        return $this->createQueryBuilder('f')
            ->where('f.owner = :owner')
            ->andWhere('f.status != :deleted')
            ->setParameter('owner', $owner)
            ->setParameter('deleted', File::STATUS_DELETED)
            ->orderBy('f.createdAt', 'DESC')
            ->getQuery()
            ->getResult();
    }

    public function findQuarantinedFiles(): array
    {
        return $this->createQueryBuilder('f')
            ->where('f.status = :quarantine')
            ->setParameter('quarantine', File::STATUS_QUARANTINE)
            ->orderBy('f.createdAt', 'DESC')
            ->getQuery()
            ->getResult();
    }

    public function findFilesByStatus(string $status): array
    {
        return $this->createQueryBuilder('f')
            ->where('f.status = :status')
            ->setParameter('status', $status)
            ->orderBy('f.createdAt', 'DESC')
            ->getQuery()
            ->getResult();
    }

    public function findLargeFiles(int $sizeBytes): array
    {
        return $this->createQueryBuilder('f')
            ->where('f.sizeBytes > :size')
            ->andWhere('f.status != :deleted')
            ->setParameter('size', $sizeBytes)
            ->setParameter('deleted', File::STATUS_DELETED)
            ->orderBy('f.sizeBytes', 'DESC')
            ->getQuery()
            ->getResult();
    }

    public function getStorageStats(): array
    {
        $qb = $this->createQueryBuilder('f')
            ->select([
                'COUNT(f.id) as totalFiles',
                'SUM(f.sizeBytes) as totalSize',
                'AVG(f.sizeBytes) as averageSize',
                'MAX(f.sizeBytes) as largestFile'
            ])
            ->where('f.status != :deleted')
            ->setParameter('deleted', File::STATUS_DELETED);

        return $qb->getQuery()->getSingleResult();
    }

    public function getStorageStatsByStatus(): array
    {
        return $this->createQueryBuilder('f')
            ->select([
                'f.status',
                'COUNT(f.id) as fileCount',
                'SUM(f.sizeBytes) as totalSize'
            ])
            ->where('f.status != :deleted')
            ->setParameter('deleted', File::STATUS_DELETED)
            ->groupBy('f.status')
            ->getQuery()
            ->getResult();
    }

    public function findFilesByDateRange(\DateTimeImmutable $start, \DateTimeImmutable $end): array
    {
        return $this->createQueryBuilder('f')
            ->where('f.createdAt BETWEEN :start AND :end')
            ->andWhere('f.status != :deleted')
            ->setParameter('start', $start)
            ->setParameter('end', $end)
            ->setParameter('deleted', File::STATUS_DELETED)
            ->orderBy('f.createdAt', 'DESC')
            ->getQuery()
            ->getResult();
    }

    public function findFilesByProject(string $projectName): array
    {
        return $this->createQueryBuilder('f')
            ->where('f.projectName = :project')
            ->andWhere('f.status != :deleted')
            ->setParameter('project', $projectName)
            ->setParameter('deleted', File::STATUS_DELETED)
            ->orderBy('f.createdAt', 'DESC')
            ->getQuery()
            ->getResult();
    }

    public function findFilesExpiringSoon(int $hours = 24): array
    {
        $expirationThreshold = (new \DateTimeImmutable())->modify("+{$hours} hours");

        return $this->createQueryBuilder('f')
            ->where('f.expiresAt IS NOT NULL')
            ->andWhere('f.expiresAt <= :threshold')
            ->andWhere('f.expiresAt > :now')
            ->andWhere('f.status != :deleted')
            ->setParameter('threshold', $expirationThreshold)
            ->setParameter('now', new \DateTimeImmutable())
            ->setParameter('deleted', File::STATUS_DELETED)
            ->orderBy('f.expiresAt', 'ASC')
            ->getQuery()
            ->getResult();
    }
}
