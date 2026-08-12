<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260721123426 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
        $this->addSql(
            'ALTER TABLE user ADD username VARCHAR(100) NOT NULL, ADD nationality VARCHAR(100) DEFAULT NULL, ' .
            'ADD city VARCHAR(100) DEFAULT NULL, ADD created_at DATETIME NOT NULL'
        );
    }

    public function down(Schema $schema): void
    {
        $this->addSql(
            'ALTER TABLE user DROP username, DROP nationality, DROP city, DROP created_at'
        );
    }
}
