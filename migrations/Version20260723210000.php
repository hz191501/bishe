<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260723210000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Aggiunge i Mi piace alle risposte e ai commenti.';
    }

    public function up(Schema $schema): void
    {
        $this->addSql(
            'CREATE TABLE response_comment_like (response_comment_id INT NOT NULL, user_id INT NOT NULL, ' .
            'INDEX IDX_F7F2A5F42B918AF3 (response_comment_id), INDEX IDX_F7F2A5F4A76ED395 (user_id), ' .
            'PRIMARY KEY (response_comment_id, user_id)) DEFAULT CHARACTER SET utf8mb4'
        );
        $this->addSql(
            'CREATE TABLE task_response_like (task_response_id INT NOT NULL, user_id INT NOT NULL, ' .
            'INDEX IDX_45D58778BD868572 (task_response_id), INDEX IDX_45D58778A76ED395 (user_id), ' .
            'PRIMARY KEY (task_response_id, user_id)) DEFAULT CHARACTER SET utf8mb4'
        );
        $this->addSql(
            'ALTER TABLE response_comment_like ADD CONSTRAINT FK_F7F2A5F42B918AF3 ' .
            'FOREIGN KEY (response_comment_id) REFERENCES response_comment (id) ON DELETE CASCADE'
        );
        $this->addSql(
            'ALTER TABLE response_comment_like ADD CONSTRAINT FK_F7F2A5F4A76ED395 ' .
            'FOREIGN KEY (user_id) REFERENCES user (id) ON DELETE CASCADE'
        );
        $this->addSql(
            'ALTER TABLE task_response_like ADD CONSTRAINT FK_45D58778BD868572 ' .
            'FOREIGN KEY (task_response_id) REFERENCES task_response (id) ON DELETE CASCADE'
        );
        $this->addSql(
            'ALTER TABLE task_response_like ADD CONSTRAINT FK_45D58778A76ED395 ' .
            'FOREIGN KEY (user_id) REFERENCES user (id) ON DELETE CASCADE'
        );
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE response_comment_like DROP FOREIGN KEY FK_F7F2A5F42B918AF3');
        $this->addSql('ALTER TABLE response_comment_like DROP FOREIGN KEY FK_F7F2A5F4A76ED395');
        $this->addSql('ALTER TABLE task_response_like DROP FOREIGN KEY FK_45D58778BD868572');
        $this->addSql('ALTER TABLE task_response_like DROP FOREIGN KEY FK_45D58778A76ED395');
        $this->addSql('DROP TABLE response_comment_like');
        $this->addSql('DROP TABLE task_response_like');
    }
}
