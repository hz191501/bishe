<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260724100000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Aggiunge un oggetto facoltativo alle lettere tra amici di penna.';
    }

    public function up(Schema $schema): void
    {
        // Il campo resta nullable per conservare tutti i messaggi già esistenti.
        $this->addSql('ALTER TABLE penpal_message ADD subject VARCHAR(150) DEFAULT NULL');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE penpal_message DROP subject');
    }
}