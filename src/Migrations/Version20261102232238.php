<?php

declare(strict_types=1);

namespace App\Migrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20261102232238 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql('CREATE TABLE event_in (uuid UUID NOT NULL, external_id VARCHAR(255) NOT NULL, type VARCHAR(100) NOT NULL, payload JSONB NOT NULL, received_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL, source_id INT NOT NULL, PRIMARY KEY (uuid))');
        $this->addSql('CREATE INDEX idx_event_in_received_at ON event_in (received_at)');
        $this->addSql('CREATE UNIQUE INDEX uniq_event_in_source_external_id ON event_in (source_id, external_id)');
        $this->addSql('CREATE INDEX IDX_91220195953C1C61 ON event_in (source_id)');
        $this->addSql('ALTER TABLE event_in ADD CONSTRAINT FK_91220195953C1C61 FOREIGN KEY (source_id) REFERENCES source (id) NOT DEFERRABLE');
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE event_in DROP CONSTRAINT FK_91220195953C1C61');
        $this->addSql('DROP TABLE event_in');
    }
}
