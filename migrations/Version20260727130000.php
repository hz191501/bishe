<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * 为现有用户表增加可选出生日期，不创建新的实体或数据表。
 */
final class Version20260727130000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Add optional birth date to user profiles';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE user ADD birth_date DATE DEFAULT NULL');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE user DROP birth_date');
    }
}
