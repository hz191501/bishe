<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260724120000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Add the shared cultural notebook for accepted penpal connections';
    }

    public function up(Schema $schema): void
    {
        $this->addSql("CREATE TABLE shared_note (id INT AUTO_INCREMENT NOT NULL, connection_id INT NOT NULL, author_id INT NOT NULL, type VARCHAR(30) NOT NULL, title VARCHAR(120) NOT NULL, content LONGTEXT NOT NULL, created_at DATETIME NOT NULL, INDEX IDX_754B918CDD03F01 (connection_id), INDEX IDX_754B918CF675F31B (author_id), PRIMARY KEY(id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB");
        $this->addSql('ALTER TABLE shared_note ADD CONSTRAINT FK_EA5496B75E7AA58C FOREIGN KEY (connection_id) REFERENCES buddy_connection (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE shared_note ADD CONSTRAINT FK_EA5496B7F675F31B FOREIGN KEY (author_id) REFERENCES user (id) ON DELETE CASCADE');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('DROP TABLE shared_note');
    }
}