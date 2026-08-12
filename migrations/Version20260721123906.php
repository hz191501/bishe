<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260721123906 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
        $this->addSql(
            'CREATE TABLE task_response (id INT AUTO_INCREMENT NOT NULL, content LONGTEXT NOT NULL, ' .
            'is_best_answer TINYINT NOT NULL, created_at DATETIME NOT NULL, updated_at DATETIME DEFAULT NULL,' .
            ' author_id INT NOT NULL, task_id INT NOT NULL, INDEX IDX_7EBBE1B7F675F31B (author_id), INDEX ' .
            'IDX_7EBBE1B78DB60186 (task_id), PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4'
        );
        $this->addSql(
            'ALTER TABLE task_response ADD CONSTRAINT FK_7EBBE1B7F675F31B FOREIGN KEY (author_id) REFERENCES ' .
            'user (id)'
        );
        $this->addSql(
            'ALTER TABLE task_response ADD CONSTRAINT FK_7EBBE1B78DB60186 FOREIGN KEY (task_id) REFERENCES ' .
            'bridge_task (id)'
        );
    }

    public function down(Schema $schema): void
    {
        $this->addSql(
            'ALTER TABLE task_response DROP FOREIGN KEY FK_7EBBE1B7F675F31B'
        );
        $this->addSql(
            'ALTER TABLE task_response DROP FOREIGN KEY FK_7EBBE1B78DB60186'
        );
        $this->addSql(
            'DROP TABLE task_response'
        );
    }
}
