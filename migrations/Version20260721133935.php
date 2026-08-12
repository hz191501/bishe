<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260721133935 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
        $this->addSql(
            'CREATE TABLE buddy_connection (id INT AUTO_INCREMENT NOT NULL, status VARCHAR(20) NOT NULL, ' .
            'created_at DATETIME NOT NULL, accepted_at DATETIME DEFAULT NULL, requester_id INT NOT NULL, ' .
            'receiver_id INT NOT NULL, source_task_id INT DEFAULT NULL, INDEX IDX_A462BEFBED442CF4 ' .
            '(requester_id), INDEX IDX_A462BEFBCD53EDB6 (receiver_id), INDEX IDX_A462BEFBC469B9EE ' .
            '(source_task_id), PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4'
        );
        $this->addSql(
            'CREATE TABLE penpal_message (id INT AUTO_INCREMENT NOT NULL, content LONGTEXT NOT NULL, ' .
            'created_at DATETIME NOT NULL, author_id INT NOT NULL, connection_id INT NOT NULL, INDEX ' .
            'IDX_34EC5DBFF675F31B (author_id), INDEX IDX_34EC5DBFDD03F01 (connection_id), PRIMARY KEY (id)) ' .
            'DEFAULT CHARACTER SET utf8mb4'
        );
        $this->addSql(
            'ALTER TABLE buddy_connection ADD CONSTRAINT FK_A462BEFBED442CF4 FOREIGN KEY (requester_id) ' .
            'REFERENCES user (id)'
        );
        $this->addSql(
            'ALTER TABLE buddy_connection ADD CONSTRAINT FK_A462BEFBCD53EDB6 FOREIGN KEY (receiver_id) ' .
            'REFERENCES user (id)'
        );
        $this->addSql(
            'ALTER TABLE buddy_connection ADD CONSTRAINT FK_A462BEFBC469B9EE FOREIGN KEY (source_task_id) ' .
            'REFERENCES bridge_task (id)'
        );
        $this->addSql(
            'ALTER TABLE penpal_message ADD CONSTRAINT FK_34EC5DBFF675F31B FOREIGN KEY (author_id) REFERENCES' .
            ' user (id)'
        );
        $this->addSql(
            'ALTER TABLE penpal_message ADD CONSTRAINT FK_34EC5DBFDD03F01 FOREIGN KEY (connection_id) ' .
            'REFERENCES buddy_connection (id)'
        );
    }

    public function down(Schema $schema): void
    {
        $this->addSql(
            'ALTER TABLE buddy_connection DROP FOREIGN KEY FK_A462BEFBED442CF4'
        );
        $this->addSql(
            'ALTER TABLE buddy_connection DROP FOREIGN KEY FK_A462BEFBCD53EDB6'
        );
        $this->addSql(
            'ALTER TABLE buddy_connection DROP FOREIGN KEY FK_A462BEFBC469B9EE'
        );
        $this->addSql(
            'ALTER TABLE penpal_message DROP FOREIGN KEY FK_34EC5DBFF675F31B'
        );
        $this->addSql(
            'ALTER TABLE penpal_message DROP FOREIGN KEY FK_34EC5DBFDD03F01'
        );
        $this->addSql(
            'DROP TABLE buddy_connection'
        );
        $this->addSql(
            'DROP TABLE penpal_message'
        );
    }
}
