<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20251011210726 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql('CREATE SEQUENCE usage_reports_id_seq INCREMENT BY 1 MINVALUE 1 START 1');
        $this->addSql('CREATE TABLE usage_reports (id INT NOT NULL, user_id INT NOT NULL, report_date DATE NOT NULL, uploads_count INT NOT NULL, uploads_size_bytes INT NOT NULL, downloads_count INT NOT NULL, downloads_size_bytes INT NOT NULL, files_shared_count INT NOT NULL, files_expired_count INT NOT NULL, files_deleted_count INT NOT NULL, file_types JSON NOT NULL, hourly_activity JSON NOT NULL, created_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL, updated_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL, PRIMARY KEY(id))');
        $this->addSql('CREATE INDEX IDX_AF9BD15AA76ED395 ON usage_reports (user_id)');
        $this->addSql('CREATE INDEX idx_user_date ON usage_reports (user_id, report_date)');
        $this->addSql('CREATE INDEX idx_report_date ON usage_reports (report_date)');
        $this->addSql('ALTER TABLE usage_reports ADD CONSTRAINT FK_AF9BD15AA76ED395 FOREIGN KEY (user_id) REFERENCES users (id) NOT DEFERRABLE INITIALLY IMMEDIATE');
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('CREATE SCHEMA public');
        $this->addSql('DROP SEQUENCE usage_reports_id_seq CASCADE');
        $this->addSql('ALTER TABLE usage_reports DROP CONSTRAINT FK_AF9BD15AA76ED395');
        $this->addSql('DROP TABLE usage_reports');
    }
}
