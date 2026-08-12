<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260723170000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Aggiunge lo stato di lettura ai messaggi tra amici di penna.';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE penpal_message ADD is_read TINYINT(1) DEFAULT 0 NOT NULL');
        $this->addSql('UPDATE penpal_message SET is_read = 1');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE penpal_message DROP is_read');
    }
}
