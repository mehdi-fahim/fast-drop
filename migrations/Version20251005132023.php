<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20251005132023 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
        // Add new columns to users table
        $this->addSql('ALTER TABLE users ADD status VARCHAR(20) NOT NULL DEFAULT \'active\'');
        $this->addSql('ALTER TABLE users ADD expires_at TIMESTAMP(0) WITHOUT TIME ZONE DEFAULT NULL');
        $this->addSql('ALTER TABLE users ADD last_login_at TIMESTAMP(0) WITHOUT TIME ZONE DEFAULT NULL');
        $this->addSql('ALTER TABLE users ADD notes TEXT DEFAULT NULL');
        $this->addSql('COMMENT ON COLUMN users.expires_at IS \'(DC2Type:datetime_immutable)\'');
        $this->addSql('COMMENT ON COLUMN users.last_login_at IS \'(DC2Type:datetime_immutable)\'');
    }

    public function down(Schema $schema): void
    {
        // Remove added columns from users table
        $this->addSql('ALTER TABLE users DROP status');
        $this->addSql('ALTER TABLE users DROP expires_at');
        $this->addSql('ALTER TABLE users DROP last_login_at');
        $this->addSql('ALTER TABLE users DROP notes');
    }
}
