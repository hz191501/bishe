<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/** 只扩展现有笔记表并增加补充表；up() 不删除或覆盖原有业务数据。 */
final class Version20260908120000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Add answer sources and private additions to shared notes';
    }

    public function isTransactional(): bool { return false; } // MySQL 的建表语句会隐式提交。

    public function up(Schema $schema): void
    {
        // 旧笔记的新增字段均为 NULL；唯一索引不限制没有来源回答的手动笔记。
        $this->addSql('ALTER TABLE shared_note ADD source_response_id INT DEFAULT NULL, ADD source_excerpt LONGTEXT DEFAULT NULL, ADD source_title VARCHAR(255) DEFAULT NULL, ADD updated_at DATETIME DEFAULT NULL');
        $this->addSql('CREATE INDEX IDX_NOTE_SOURCE ON shared_note (source_response_id)');
        $this->addSql('CREATE UNIQUE INDEX uniq_note_connection_response ON shared_note (connection_id, source_response_id)');
        $this->addSql('ALTER TABLE shared_note ADD CONSTRAINT FK_NOTE_SOURCE FOREIGN KEY (source_response_id) REFERENCES task_response (id) ON DELETE SET NULL');
        $this->addSql('CREATE TABLE shared_note_comment (id INT AUTO_INCREMENT NOT NULL, note_id INT NOT NULL, author_id INT NOT NULL, content LONGTEXT NOT NULL, created_at DATETIME NOT NULL, INDEX IDX_NOTE_COMMENT_NOTE (note_id), INDEX IDX_NOTE_COMMENT_AUTHOR (author_id), PRIMARY KEY(id)) DEFAULT CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci ENGINE = InnoDB');
        $this->addSql('ALTER TABLE shared_note_comment ADD CONSTRAINT FK_NOTE_COMMENT_NOTE FOREIGN KEY (note_id) REFERENCES shared_note (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE shared_note_comment ADD CONSTRAINT FK_NOTE_COMMENT_AUTHOR FOREIGN KEY (author_id) REFERENCES user (id) ON DELETE CASCADE');
    }

    public function down(Schema $schema): void
    {
        // 回退会失去本功能的来源及留言，因此正常使用时不要执行 down。
        $this->addSql('DROP TABLE shared_note_comment');
        $this->addSql('ALTER TABLE shared_note DROP FOREIGN KEY FK_NOTE_SOURCE');
        $this->addSql('DROP INDEX uniq_note_connection_response ON shared_note');
        $this->addSql('DROP INDEX IDX_NOTE_SOURCE ON shared_note');
        $this->addSql('ALTER TABLE shared_note DROP source_response_id, DROP source_excerpt, DROP source_title, DROP updated_at');
    }
}
