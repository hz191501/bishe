<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260721123752 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
        $this->addSql(
            'CREATE TABLE bridge_task (id INT AUTO_INCREMENT NOT NULL, title VARCHAR(255) NOT NULL, ' .
            'description LONGTEXT NOT NULL, status VARCHAR(30) NOT NULL, created_at DATETIME NOT NULL, ' .
            'updated_at DATETIME DEFAULT NULL, resolved_at DATETIME DEFAULT NULL, author_id INT NOT NULL, ' .
            'category_id INT NOT NULL, INDEX IDX_EAB3E2AFF675F31B (author_id), INDEX IDX_EAB3E2AF12469DE2 ' .
            '(category_id), PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4'
        );
        $this->addSql(
            'ALTER TABLE bridge_task ADD CONSTRAINT FK_EAB3E2AFF675F31B FOREIGN KEY (author_id) REFERENCES ' .
            'user (id)'
        );
        $this->addSql(
            'ALTER TABLE bridge_task ADD CONSTRAINT FK_EAB3E2AF12469DE2 FOREIGN KEY (category_id) REFERENCES ' .
            'category (id)'
        );
    }

    public function down(Schema $schema): void
    {
        $this->addSql(
            'ALTER TABLE bridge_task DROP FOREIGN KEY FK_EAB3E2AFF675F31B'
        );
        $this->addSql(
            'ALTER TABLE bridge_task DROP FOREIGN KEY FK_EAB3E2AF12469DE2'
        );
        $this->addSql(
            'DROP TABLE bridge_task'
        );
    }
}
