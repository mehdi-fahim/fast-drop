<?php

namespace App\Command;

use App\Repository\FileRepository;
use App\Repository\DownloadTokenRepository;
use App\Service\StorageService;
use App\Service\AuditService;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

#[AsCommand(
    name: 'app:purge-expired-files',
    description: 'Purge expired files and tokens from the system',
)]
class PurgeExpiredFilesCommand extends Command
{
    public function __construct(
        private FileRepository $fileRepository,
        private DownloadTokenRepository $tokenRepository,
        private StorageService $storageService,
        private AuditService $auditService,
        private EntityManagerInterface $entityManager
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this
            ->addOption('dry-run', null, InputOption::VALUE_NONE, 'Show what would be deleted without actually deleting')
            ->addOption('days', 'd', InputOption::VALUE_REQUIRED, 'Number of days after expiration to wait before purging', 7)
            ->setHelp('This command purges expired files and tokens from the system.');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $dryRun = $input->getOption('dry-run');
        $days = (int)$input->getOption('days');

        $io->title('FastDrop - Purge des fichiers expirés');

        if ($dryRun) {
            $io->warning('Mode DRY-RUN activé - Aucun fichier ne sera supprimé');
        }

        $cutoffDate = new \DateTimeImmutable("-{$days} days");
        $io->info("Suppression des fichiers expirés avant le {$cutoffDate->format('Y-m-d H:i:s')}");

        // Find expired files
        $expiredFiles = $this->fileRepository->createQueryBuilder('f')
            ->where('f.expiresAt IS NOT NULL')
            ->andWhere('f.expiresAt < :cutoffDate')
            ->andWhere('f.status != :deleted')
            ->setParameter('cutoffDate', $cutoffDate)
            ->setParameter('deleted', 'deleted')
            ->getQuery()
            ->getResult();

        $io->info(sprintf('Trouvé %d fichiers expirés', count($expiredFiles)));

        $deletedFiles = 0;
        $freedSpace = 0;
        $errors = [];

        foreach ($expiredFiles as $file) {
            try {
                $io->writeln(sprintf(
                    'Traitement: %s (expiré le %s, %s)',
                    $file->getFilename(),
                    $file->getExpiresAt()->format('Y-m-d H:i:s'),
                    $file->getFormattedSize()
                ));

                if (!$dryRun) {
                    // Delete from storage
                    if ($this->storageService->exists($file->getStoragePath())) {
                        $this->storageService->delete($file->getStoragePath());
                    }

                    // Update user quota
                    $owner = $file->getOwner();
                    $owner->setQuotaUsedBytes($owner->getQuotaUsedBytes() - $file->getSizeBytes());

                    // Log deletion
                    $this->auditService->logDelete($owner, $file, [
                        'reason' => 'expired_auto_purge',
                        'expired_at' => $file->getExpiresAt()->format('Y-m-d H:i:s')
                    ]);

                    // Delete file entity
                    $this->entityManager->remove($file);
                    $deletedFiles++;
                    $freedSpace += $file->getSizeBytes();
                } else {
                    $deletedFiles++;
                    $freedSpace += $file->getSizeBytes();
                }
            } catch (\Exception $e) {
                $errors[] = sprintf('Erreur avec %s: %s', $file->getFilename(), $e->getMessage());
                $io->error($errors[count($errors) - 1]);
            }
        }

        if (!$dryRun) {
            $this->entityManager->flush();
        }

        // Clean up expired tokens
        $expiredTokens = $this->tokenRepository->findExpiredTokens();
        $deletedTokens = 0;

        foreach ($expiredTokens as $token) {
            if (!$token->isRevoked()) {
                if (!$dryRun) {
                    $token->setRevoked(true);
                    $deletedTokens++;
                } else {
                    $deletedTokens++;
                }
            }
        }

        if (!$dryRun && $deletedTokens > 0) {
            $this->entityManager->flush();
        }

        // Summary
        $io->success(sprintf(
            'Purge terminée: %d fichiers supprimés, %d tokens nettoyés, %s libérés',
            $deletedFiles,
            $deletedTokens,
            $this->formatBytes($freedSpace)
        ));

        if (!empty($errors)) {
            $io->warning(sprintf('Erreurs rencontrées: %d', count($errors)));
            foreach ($errors as $error) {
                $io->writeln("  - {$error}");
            }
        }

        return Command::SUCCESS;
    }

    private function formatBytes(int $bytes): string
    {
        if ($bytes === 0) return '0 B';
        
        $units = ['B', 'KB', 'MB', 'GB', 'TB'];
        $bytes = max($bytes, 0);
        $pow = floor(($bytes ? log($bytes) : 0) / log(1024));
        $pow = min($pow, count($units) - 1);
        
        $bytes /= pow(1024, $pow);
        
        return round($bytes, 2) . ' ' . $units[$pow];
    }
}
