<?php

namespace App\Command;

use App\Entity\User;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Question\Question;
use Symfony\Component\Console\Style\SymfonyStyle;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;

#[AsCommand(
    name: 'app:create-admin-user',
    description: 'Create an admin user for the FastDrop platform',
)]
class CreateAdminUserCommand extends Command
{
    public function __construct(
        private EntityManagerInterface $entityManager,
        private UserPasswordHasherInterface $passwordHasher
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this
            ->addOption('email', null, InputOption::VALUE_REQUIRED, 'Admin email address')
            ->addOption('password', null, InputOption::VALUE_REQUIRED, 'Admin password')
            ->addOption('quota', null, InputOption::VALUE_REQUIRED, 'Storage quota in GB (0 for unlimited)', 0)
            ->setHelp('This command creates an admin user for the FastDrop platform.');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $helper = $this->getHelper('question');

        $io->title('FastDrop - Création d\'un utilisateur administrateur');

        // Get email
        $email = $input->getOption('email');
        if (!$email) {
            $question = new Question('Email de l\'administrateur: ');
            $email = $helper->ask($input, $output, $question);
        }

        if (!$email || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $io->error('Email invalide');
            return Command::FAILURE;
        }

        // Check if user already exists
        $existingUser = $this->entityManager->getRepository(User::class)->findByEmail($email);
        if ($existingUser) {
            $io->error('Un utilisateur avec cet email existe déjà');
            return Command::FAILURE;
        }

        // Get password
        $password = $input->getOption('password');
        if (!$password) {
            $question = new Question('Mot de passe: ');
            $question->setHidden(true);
            $password = $helper->ask($input, $output, $question);
        }

        if (strlen($password) < 8) {
            $io->error('Le mot de passe doit contenir au moins 8 caractères');
            return Command::FAILURE;
        }

        // Get quota
        $quota = $input->getOption('quota');
        $quotaBytes = $quota > 0 ? $quota * 1024 * 1024 * 1024 : null;

        // Create user
        $user = new User();
        $user->setEmail($email);
        $user->setPassword($this->passwordHasher->hashPassword($user, $password));
        $user->setRoles([User::ROLE_ADMIN, User::ROLE_USER, User::ROLE_UPLOADER, User::ROLE_VIEWER]);
        $user->setQuotaTotalBytes($quotaBytes);
        $user->setQuotaUsedBytes(0);

        $this->entityManager->persist($user);
        $this->entityManager->flush();

        $io->success(sprintf(
            'Utilisateur administrateur créé avec succès: %s (quota: %s)',
            $email,
            $quota > 0 ? $quota . ' GB' : 'illimité'
        ));

        $io->note([
            'Vous pouvez maintenant vous connecter avec ces identifiants.',
            'Assurez-vous de configurer votre environnement de production:',
            '- Changez APP_SECRET dans votre fichier .env',
            '- Changez HMAC_SECRET pour la signature des tokens',
            '- Configurez votre base de données PostgreSQL',
            '- Configurez Redis pour les verrous et le cache',
            '- Configurez votre stockage (local ou S3)',
        ]);

        return Command::SUCCESS;
    }
}
