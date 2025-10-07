<?php

namespace App\Command;

use App\Entity\File;
use App\Repository\UserRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

#[AsCommand(
    name: 'app:recalculate-quotas',
    description: 'Recalcule les quotas utilisés par chaque utilisateur',
)]
class RecalculateQuotasCommand extends Command
{
    public function __construct(
        private UserRepository $userRepository,
        private EntityManagerInterface $entityManager
    ) {
        parent::__construct();
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);

        $io->title('Recalcul des quotas utilisateurs');

        $users = $this->userRepository->findAll();
        $totalUsers = count($users);
        $updated = 0;

        $io->progressStart($totalUsers);

        foreach ($users as $user) {
            // Calculer l'espace réellement utilisé par les fichiers de l'utilisateur
            $qb = $this->entityManager->createQueryBuilder();
            $qb->select('SUM(f.sizeBytes) as totalSize')
                ->from(File::class, 'f')
                ->where('f.owner = :user')
                ->andWhere('f.status != :deleted')
                ->setParameter('user', $user)
                ->setParameter('deleted', File::STATUS_DELETED);
            
            $result = $qb->getQuery()->getSingleScalarResult();
            $actualUsed = $result ? (int)$result : 0;

            $oldUsed = $user->getQuotaUsedBytes();
            
            if ($oldUsed !== $actualUsed) {
                $user->setQuotaUsedBytes($actualUsed);
                $updated++;
                
                $io->writeln(sprintf(
                    "\n  - %s: %s MB → %s MB",
                    $user->getEmail(),
                    number_format($oldUsed / 1024 / 1024, 2),
                    number_format($actualUsed / 1024 / 1024, 2)
                ));
            }

            $io->progressAdvance();
        }

        $this->entityManager->flush();
        $io->progressFinish();

        $io->success(sprintf(
            'Recalcul terminé ! %d/%d utilisateurs mis à jour.',
            $updated,
            $totalUsers
        ));

        return Command::SUCCESS;
    }
}

