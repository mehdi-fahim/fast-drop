<?php

namespace App\Command;

use App\Service\AuditService;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

#[AsCommand(
    name: 'app:cleanup-audit-logs',
    description: 'Clean up old audit logs to save database space',
)]
class CleanupAuditLogsCommand extends Command
{
    public function __construct(
        private AuditService $auditService
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this
            ->addOption('days', 'd', InputOption::VALUE_REQUIRED, 'Number of days to keep audit logs', 365)
            ->addOption('dry-run', null, InputOption::VALUE_NONE, 'Show what would be deleted without actually deleting')
            ->setHelp('This command removes old audit logs from the database.');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $days = (int)$input->getOption('days');
        $dryRun = $input->getOption('dry-run');

        $io->title('FastDrop - Nettoyage des logs d\'audit');

        if ($dryRun) {
            $io->warning('Mode DRY-RUN activé - Aucun log ne sera supprimé');
        }

        $io->info("Suppression des logs plus anciens que {$days} jours");

        if ($dryRun) {
            // In dry-run mode, we would need to count the logs that would be deleted
            // For now, just show the configuration
            $io->info('Configuration: Garder les logs des ' . $days . ' derniers jours');
            $io->success('Mode DRY-RUN terminé');
            return Command::SUCCESS;
        }

        try {
            $deletedCount = $this->auditService->cleanupOldLogs($days);
            
            $io->success(sprintf(
                'Nettoyage terminé: %d logs supprimés (gardé les logs des %d derniers jours)',
                $deletedCount,
                $days
            ));

            return Command::SUCCESS;
        } catch (\Exception $e) {
            $io->error('Erreur lors du nettoyage: ' . $e->getMessage());
            return Command::FAILURE;
        }
    }
}
