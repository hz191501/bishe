<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/*
 * 数据库迁移：给用户资料增加可选的性别字段。
 * 字段允许为空，因此不会影响已经注册的用户。
 */
final class Version20260727120000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Add optional gender field to user profiles';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE user ADD gender VARCHAR(30) DEFAULT NULL');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE user DROP gender');
    }
}
