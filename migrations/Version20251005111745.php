<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20251005111745 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql('CREATE SEQUENCE audit_logs_id_seq INCREMENT BY 1 MINVALUE 1 START 1');
        $this->addSql('CREATE SEQUENCE download_tokens_id_seq INCREMENT BY 1 MINVALUE 1 START 1');
        $this->addSql('CREATE SEQUENCE files_id_seq INCREMENT BY 1 MINVALUE 1 START 1');
        $this->addSql('CREATE SEQUENCE users_id_seq INCREMENT BY 1 MINVALUE 1 START 1');
        $this->addSql('CREATE TABLE audit_logs (id INT NOT NULL, user_id INT DEFAULT NULL, file_id INT DEFAULT NULL, action VARCHAR(50) NOT NULL, ip VARCHAR(45) DEFAULT NULL, user_agent TEXT DEFAULT NULL, metadata JSON DEFAULT NULL, timestamp TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL, resource_id VARCHAR(255) DEFAULT NULL, resource_type VARCHAR(255) DEFAULT NULL, PRIMARY KEY(id))');
        $this->addSql('CREATE INDEX IDX_D62F2858A76ED395 ON audit_logs (user_id)');
        $this->addSql('CREATE INDEX IDX_D62F285893CB796C ON audit_logs (file_id)');
        $this->addSql('COMMENT ON COLUMN audit_logs.timestamp IS \'(DC2Type:datetime_immutable)\'');
        $this->addSql('CREATE TABLE download_tokens (id INT NOT NULL, file_id INT NOT NULL, created_by_id INT NOT NULL, token_hash VARCHAR(64) NOT NULL, expires_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL, max_downloads INT NOT NULL, downloads_count INT NOT NULL, password_hash VARCHAR(255) DEFAULT NULL, ip_whitelist JSON DEFAULT NULL, revoked BOOLEAN NOT NULL, created_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL, last_used_at TIMESTAMP(0) WITHOUT TIME ZONE DEFAULT NULL, PRIMARY KEY(id))');
        $this->addSql('CREATE INDEX IDX_68A294A593CB796C ON download_tokens (file_id)');
        $this->addSql('CREATE INDEX IDX_68A294A5B03A8386 ON download_tokens (created_by_id)');
        $this->addSql('COMMENT ON COLUMN download_tokens.expires_at IS \'(DC2Type:datetime_immutable)\'');
        $this->addSql('COMMENT ON COLUMN download_tokens.created_at IS \'(DC2Type:datetime_immutable)\'');
        $this->addSql('COMMENT ON COLUMN download_tokens.last_used_at IS \'(DC2Type:datetime_immutable)\'');
        $this->addSql('CREATE TABLE files (id INT NOT NULL, owner_id INT NOT NULL, filename VARCHAR(255) NOT NULL, storage_path VARCHAR(500) NOT NULL, size_bytes BIGINT NOT NULL, checksum VARCHAR(64) DEFAULT NULL, status VARCHAR(20) NOT NULL, description TEXT DEFAULT NULL, project_name VARCHAR(255) DEFAULT NULL, metadata JSON DEFAULT NULL, created_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL, expires_at TIMESTAMP(0) WITHOUT TIME ZONE DEFAULT NULL, updated_at TIMESTAMP(0) WITHOUT TIME ZONE DEFAULT NULL, recipients JSON DEFAULT NULL, PRIMARY KEY(id))');
        $this->addSql('CREATE INDEX IDX_63540597E3C61F9 ON files (owner_id)');
        $this->addSql('COMMENT ON COLUMN files.created_at IS \'(DC2Type:datetime_immutable)\'');
        $this->addSql('COMMENT ON COLUMN files.expires_at IS \'(DC2Type:datetime_immutable)\'');
        $this->addSql('COMMENT ON COLUMN files.updated_at IS \'(DC2Type:datetime_immutable)\'');
        $this->addSql('CREATE TABLE users (id INT NOT NULL, email VARCHAR(180) NOT NULL, roles JSON NOT NULL, password VARCHAR(255) NOT NULL, quota_total_bytes BIGINT DEFAULT NULL, quota_used_bytes BIGINT DEFAULT 0 NOT NULL, created_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL, PRIMARY KEY(id))');
        $this->addSql('CREATE UNIQUE INDEX UNIQ_1483A5E9E7927C74 ON users (email)');
        $this->addSql('COMMENT ON COLUMN users.created_at IS \'(DC2Type:datetime_immutable)\'');
        $this->addSql('CREATE TABLE messenger_messages (id BIGSERIAL NOT NULL, body TEXT NOT NULL, headers TEXT NOT NULL, queue_name VARCHAR(190) NOT NULL, created_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL, available_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL, delivered_at TIMESTAMP(0) WITHOUT TIME ZONE DEFAULT NULL, PRIMARY KEY(id))');
        $this->addSql('CREATE INDEX IDX_75EA56E0FB7336F0 ON messenger_messages (queue_name)');
        $this->addSql('CREATE INDEX IDX_75EA56E0E3BD61CE ON messenger_messages (available_at)');
        $this->addSql('CREATE INDEX IDX_75EA56E016BA31DB ON messenger_messages (delivered_at)');
        $this->addSql('COMMENT ON COLUMN messenger_messages.created_at IS \'(DC2Type:datetime_immutable)\'');
        $this->addSql('COMMENT ON COLUMN messenger_messages.available_at IS \'(DC2Type:datetime_immutable)\'');
        $this->addSql('COMMENT ON COLUMN messenger_messages.delivered_at IS \'(DC2Type:datetime_immutable)\'');
        $this->addSql('CREATE OR REPLACE FUNCTION notify_messenger_messages() RETURNS TRIGGER AS $$
            BEGIN
                PERFORM pg_notify(\'messenger_messages\', NEW.queue_name::text);
                RETURN NEW;
            END;
        $$ LANGUAGE plpgsql;');
        $this->addSql('DROP TRIGGER IF EXISTS notify_trigger ON messenger_messages;');
        $this->addSql('CREATE TRIGGER notify_trigger AFTER INSERT OR UPDATE ON messenger_messages FOR EACH ROW EXECUTE PROCEDURE notify_messenger_messages();');
        $this->addSql('ALTER TABLE audit_logs ADD CONSTRAINT FK_D62F2858A76ED395 FOREIGN KEY (user_id) REFERENCES users (id) NOT DEFERRABLE INITIALLY IMMEDIATE');
        $this->addSql('ALTER TABLE audit_logs ADD CONSTRAINT FK_D62F285893CB796C FOREIGN KEY (file_id) REFERENCES files (id) NOT DEFERRABLE INITIALLY IMMEDIATE');
        $this->addSql('ALTER TABLE download_tokens ADD CONSTRAINT FK_68A294A593CB796C FOREIGN KEY (file_id) REFERENCES files (id) NOT DEFERRABLE INITIALLY IMMEDIATE');
        $this->addSql('ALTER TABLE download_tokens ADD CONSTRAINT FK_68A294A5B03A8386 FOREIGN KEY (created_by_id) REFERENCES users (id) NOT DEFERRABLE INITIALLY IMMEDIATE');
        $this->addSql('ALTER TABLE files ADD CONSTRAINT FK_63540597E3C61F9 FOREIGN KEY (owner_id) REFERENCES users (id) NOT DEFERRABLE INITIALLY IMMEDIATE');
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('CREATE SCHEMA public');
        $this->addSql('DROP SEQUENCE audit_logs_id_seq CASCADE');
        $this->addSql('DROP SEQUENCE download_tokens_id_seq CASCADE');
        $this->addSql('DROP SEQUENCE files_id_seq CASCADE');
        $this->addSql('DROP SEQUENCE users_id_seq CASCADE');
        $this->addSql('ALTER TABLE audit_logs DROP CONSTRAINT FK_D62F2858A76ED395');
        $this->addSql('ALTER TABLE audit_logs DROP CONSTRAINT FK_D62F285893CB796C');
        $this->addSql('ALTER TABLE download_tokens DROP CONSTRAINT FK_68A294A593CB796C');
        $this->addSql('ALTER TABLE download_tokens DROP CONSTRAINT FK_68A294A5B03A8386');
        $this->addSql('ALTER TABLE files DROP CONSTRAINT FK_63540597E3C61F9');
        $this->addSql('DROP TABLE audit_logs');
        $this->addSql('DROP TABLE download_tokens');
        $this->addSql('DROP TABLE files');
        $this->addSql('DROP TABLE users');
        $this->addSql('DROP TABLE messenger_messages');
    }
}
