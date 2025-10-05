<?php

namespace App\Repository;

use App\Entity\DownloadToken;
use App\Entity\File;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<DownloadToken>
 *
 * @method DownloadToken|null find($id, $lockMode = null, $lockVersion = null)
 * @method DownloadToken|null findOneBy(array $criteria, array $orderBy = null)
 * @method DownloadToken[]    findAll()
 * @method DownloadToken[]    findBy(array $criteria, array $orderBy = null, $limit = null, $offset = null)
 */
class DownloadTokenRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, DownloadToken::class);
    }

    public function findByTokenHash(string $tokenHash): ?DownloadToken
    {
        return $this->findOneBy(['tokenHash' => $tokenHash]);
    }

    public function findActiveTokens(): array
    {
        return $this->createQueryBuilder('dt')
            ->where('dt.revoked = false')
            ->andWhere('dt.expiresAt > :now')
            ->andWhere('dt.downloadsCount < dt.maxDownloads')
            ->setParameter('now', new \DateTimeImmutable())
            ->orderBy('dt.createdAt', 'DESC')
            ->getQuery()
            ->getResult();
    }

    public function findExpiredTokens(): array
    {
        return $this->createQueryBuilder('dt')
            ->where('dt.expiresAt <= :now')
            ->setParameter('now', new \DateTimeImmutable())
            ->orderBy('dt.expiresAt', 'DESC')
            ->getQuery()
            ->getResult();
    }

    public function findTokensByFile(File $file): array
    {
        return $this->createQueryBuilder('dt')
            ->where('dt.file = :file')
            ->setParameter('file', $file)
            ->orderBy('dt.createdAt', 'DESC')
            ->getQuery()
            ->getResult();
    }

    public function findExhaustedTokens(): array
    {
        return $this->createQueryBuilder('dt')
            ->where('dt.downloadsCount >= dt.maxDownloads')
            ->andWhere('dt.revoked = false')
            ->orderBy('dt.lastUsedAt', 'DESC')
            ->getQuery()
            ->getResult();
    }

    public function findRecentlyUsedTokens(int $hours = 24): array
    {
        $threshold = (new \DateTimeImmutable())->modify("-{$hours} hours");

        return $this->createQueryBuilder('dt')
            ->where('dt.lastUsedAt IS NOT NULL')
            ->andWhere('dt.lastUsedAt >= :threshold')
            ->setParameter('threshold', $threshold)
            ->orderBy('dt.lastUsedAt', 'DESC')
            ->getQuery()
            ->getResult();
    }

    public function getTokenStats(): array
    {
        $qb = $this->createQueryBuilder('dt')
            ->select([
                'COUNT(dt.id) as totalTokens',
                'SUM(dt.downloadsCount) as totalDownloads',
                'AVG(dt.downloadsCount) as averageDownloads'
            ]);

        return $qb->getQuery()->getSingleResult();
    }

    public function getTokenStatsByStatus(): array
    {
        return $this->createQueryBuilder('dt')
            ->select([
                'CASE 
                    WHEN dt.revoked = true THEN \'revoked\'
                    WHEN dt.expiresAt <= :now THEN \'expired\'
                    WHEN dt.downloadsCount >= dt.maxDownloads THEN \'exhausted\'
                    ELSE \'active\'
                END as status',
                'COUNT(dt.id) as tokenCount',
                'SUM(dt.downloadsCount) as totalDownloads'
            ])
            ->setParameter('now', new \DateTimeImmutable())
            ->groupBy('status')
            ->getQuery()
            ->getResult();
    }

    public function findTokensExpiringSoon(int $hours = 24): array
    {
        $expirationThreshold = (new \DateTimeImmutable())->modify("+{$hours} hours");

        return $this->createQueryBuilder('dt')
            ->where('dt.expiresAt IS NOT NULL')
            ->andWhere('dt.expiresAt <= :threshold')
            ->andWhere('dt.expiresAt > :now')
            ->andWhere('dt.revoked = false')
            ->setParameter('threshold', $expirationThreshold)
            ->setParameter('now', new \DateTimeImmutable())
            ->orderBy('dt.expiresAt', 'ASC')
            ->getQuery()
            ->getResult();
    }

    public function findHighUsageTokens(int $downloadThreshold = 100): array
    {
        return $this->createQueryBuilder('dt')
            ->where('dt.downloadsCount >= :threshold')
            ->setParameter('threshold', $downloadThreshold)
            ->orderBy('dt.downloadsCount', 'DESC')
            ->getQuery()
            ->getResult();
    }
}
