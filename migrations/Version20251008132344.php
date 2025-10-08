<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20251008132344 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        
        // First, update NULL values to default quota (1GB = 1073741824 bytes)
        $this->addSql("UPDATE users SET quota_total_bytes = 1073741824 WHERE quota_total_bytes IS NULL");
        
        // Then apply the schema changes
        $this->addSql('ALTER TABLE files ALTER size_bytes TYPE BIGINT');
        $this->addSql('ALTER TABLE users ALTER quota_total_bytes TYPE BIGINT');
        $this->addSql('ALTER TABLE users ALTER quota_total_bytes SET NOT NULL');
        $this->addSql('ALTER TABLE users ALTER quota_used_bytes TYPE BIGINT');
        $this->addSql('ALTER TABLE users ALTER quota_used_bytes DROP DEFAULT');
        $this->addSql('ALTER TABLE users ALTER status DROP DEFAULT');
        $this->addSql('ALTER TABLE users ALTER notes TYPE VARCHAR(255)');
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('CREATE SCHEMA public');
        $this->addSql('ALTER TABLE users ALTER quota_total_bytes TYPE BIGINT');
        $this->addSql('ALTER TABLE users ALTER quota_total_bytes DROP NOT NULL');
        $this->addSql('ALTER TABLE users ALTER quota_used_bytes TYPE BIGINT');
        $this->addSql('ALTER TABLE users ALTER quota_used_bytes SET DEFAULT 0');
        $this->addSql('ALTER TABLE users ALTER status SET DEFAULT \'active\'');
        $this->addSql('ALTER TABLE users ALTER notes TYPE TEXT');
        $this->addSql('ALTER TABLE files ALTER size_bytes TYPE BIGINT');
    }
}
