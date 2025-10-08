<?php

namespace App\Command;

use App\Entity\File;
use App\Entity\User;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

#[AsCommand(
    name: 'app:fix-quotas',
    description: 'Corrige les quotas utilisateurs en les recalculant à partir des fichiers existants',
)]
class FixQuotasCommand extends Command
{
    public function __construct(
        private EntityManagerInterface $entityManager
    ) {
        parent::__construct();
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);

        $io->title('🔧 Correction des quotas utilisateurs');

        // Get all users
        $users = $this->entityManager->getRepository(User::class)->findAll();
        $fixed = 0;

        foreach ($users as $user) {
            $io->section("👤 Utilisateur: {$user->getEmail()}");
            
            // Calculate real used space from files
            $qb = $this->entityManager->createQueryBuilder();
            $qb->select('SUM(f.sizeBytes) as totalSize')
               ->from(File::class, 'f')
               ->where('f.owner = :owner')
               ->andWhere('f.status != :deleted')
               ->setParameter('owner', $user)
               ->setParameter('deleted', File::STATUS_DELETED);
            
            $result = $qb->getQuery()->getSingleScalarResult();
            $realUsedBytes = $result ? (int)$result : 0;
            
            $oldQuotaUsed = $user->getQuotaUsedBytes();
            
            $io->text([
                "📊 Ancien quota utilisé: " . number_format($oldQuotaUsed / 1024 / 1024 / 1024, 2) . " GB",
                "📊 Nouveau quota utilisé: " . number_format($realUsedBytes / 1024 / 1024 / 1024, 2) . " GB",
            ]);
            
            if ($oldQuotaUsed !== $realUsedBytes) {
                // Update quota
                $user->setQuotaUsedBytes($realUsedBytes);
                $fixed++;
                $io->success("✅ Quota corrigé!");
            } else {
                $io->info("ℹ️  Quota déjà correct");
            }
            
            $io->newLine();
        }

        // Save changes
        if ($fixed > 0) {
            $this->entityManager->flush();
            $io->success("🎉 {$fixed} quotas ont été corrigés!");
        } else {
            $io->info("ℹ️  Aucun quota à corriger");
        }

        // Verify results
        $io->section('📋 Vérification des résultats');
        foreach ($users as $user) {
            $usedGB = $user->getQuotaUsedBytes() / 1024 / 1024 / 1024;
            $totalGB = $user->getQuotaTotalBytes() / 1024 / 1024 / 1024;
            $percentage = $totalGB > 0 ? ($usedGB / $totalGB) * 100 : 0;
            $io->text("{$user->getEmail()}: {$usedGB} GB / {$totalGB} GB ({$percentage}%)");
        }

        return Command::SUCCESS;
    }
}
