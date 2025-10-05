<?php

namespace App\Repository;

use App\Entity\User;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;
use Symfony\Component\Security\Core\Exception\UnsupportedUserException;
use Symfony\Component\Security\Core\User\PasswordAuthenticatedUserInterface;
use Symfony\Component\Security\Core\User\PasswordUpgraderInterface;

/**
 * @extends ServiceEntityRepository<User>
 *
 * @implements PasswordUpgraderInterface<User>
 *
 * @method User|null find($id, $lockMode = null, $lockVersion = null)
 * @method User|null findOneBy(array $criteria, array $orderBy = null)
 * @method User[]    findAll()
 * @method User[]    findBy(array $criteria, array $orderBy = null, $limit = null, $offset = null)
 */
class UserRepository extends ServiceEntityRepository implements PasswordUpgraderInterface
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, User::class);
    }

    /**
     * Used to upgrade (rehash) the user's password automatically over time.
     */
    public function upgradePassword(PasswordAuthenticatedUserInterface $user, string $newHashedPassword): void
    {
        if (!$user instanceof User) {
            throw new UnsupportedUserException(sprintf('Instances of "%s" are not supported.', $user::class));
        }

        $user->setPassword($newHashedPassword);
        $this->getEntityManager()->persist($user);
        $this->getEntityManager()->flush();
    }

    public function findByEmail(string $email): ?User
    {
        return $this->findOneBy(['email' => $email]);
    }

    public function findAdmins(): array
    {
        return $this->createQueryBuilder('u')
            ->where('u.roles LIKE :role')
            ->setParameter('role', '%ROLE_ADMIN%')
            ->getQuery()
            ->getResult();
    }

    public function findUsersWithQuotaExceeded(): array
    {
        return $this->createQueryBuilder('u')
            ->where('u.quotaTotalBytes IS NOT NULL')
            ->andWhere('u.quotaUsedBytes > u.quotaTotalBytes')
            ->getQuery()
            ->getResult();
    }

    public function getTotalStorageUsed(): int
    {
        $result = $this->createQueryBuilder('u')
            ->select('SUM(u.quotaUsedBytes) as total')
            ->getQuery()
            ->getSingleScalarResult();

        return (int) ($result ?? 0);
    }

    public function getStorageStatsByUser(): array
    {
        return $this->createQueryBuilder('u')
            ->select([
                'u.email',
                'u.quotaTotalBytes',
                'u.quotaUsedBytes',
                'COUNT(f.id) as fileCount'
            ])
            ->leftJoin('u.files', 'f')
            ->groupBy('u.id')
            ->orderBy('u.quotaUsedBytes', 'DESC')
            ->getQuery()
            ->getResult();
    }
}
