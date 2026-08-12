<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260723200000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Crea i commenti di primo livello sotto le risposte.';
    }

    public function up(Schema $schema): void
    {
        $this->addSql(
            'CREATE TABLE response_comment (id INT AUTO_INCREMENT NOT NULL, content LONGTEXT NOT NULL, ' .
            'created_at DATETIME NOT NULL, author_id INT NOT NULL, response_id INT NOT NULL, ' .
            'INDEX IDX_3FCE1412F675F31B (author_id), INDEX IDX_3FCE1412FBF32840 (response_id), ' .
            'PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4'
        );
        $this->addSql(
            'ALTER TABLE response_comment ADD CONSTRAINT FK_EB613873F675F31B ' .
            'FOREIGN KEY (author_id) REFERENCES user (id)'
        );
        $this->addSql(
            'ALTER TABLE response_comment ADD CONSTRAINT FK_EB6138731E27F6BF ' .
            'FOREIGN KEY (response_id) REFERENCES task_response (id)'
        );
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE response_comment DROP FOREIGN KEY FK_EB613873F675F31B');
        $this->addSql('ALTER TABLE response_comment DROP FOREIGN KEY FK_EB6138731E27F6BF');
        $this->addSql('DROP TABLE response_comment');
    }
}
