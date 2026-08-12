<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;


final class Version20260724150000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Add language and interest fields used by penpal matching';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE user ADD spoken_languages VARCHAR(255) DEFAULT NULL, ADD learning_languages VARCHAR(255) DEFAULT NULL, ADD interests VARCHAR(500) DEFAULT NULL');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE user DROP spoken_languages, DROP learning_languages, DROP interests');
    }
}