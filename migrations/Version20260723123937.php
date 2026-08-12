<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260723123937 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Aggiunge le notifiche della comunità e i Mi piace alle richieste.';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('CREATE TABLE bridge_task_like (bridge_task_id INT NOT NULL, user_id INT NOT NULL, INDEX IDX_1CCEEA738A45ACDF (bridge_task_id), INDEX IDX_1CCEEA73A76ED395 (user_id), PRIMARY KEY (bridge_task_id, user_id)) DEFAULT CHARACTER SET utf8mb4');
        $this->addSql('CREATE TABLE community_notification (id INT AUTO_INCREMENT NOT NULL, type VARCHAR(50) NOT NULL, message VARCHAR(255) NOT NULL, target_path VARCHAR(255) NOT NULL, created_at DATETIME NOT NULL, read_at DATETIME DEFAULT NULL, recipient_id INT NOT NULL, actor_id INT NOT NULL, INDEX IDX_E045CE55E92F8F78 (recipient_id), INDEX IDX_E045CE5510DAF24A (actor_id), PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4');
        $this->addSql('ALTER TABLE bridge_task_like ADD CONSTRAINT FK_1CCEEA738A45ACDF FOREIGN KEY (bridge_task_id) REFERENCES bridge_task (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE bridge_task_like ADD CONSTRAINT FK_1CCEEA73A76ED395 FOREIGN KEY (user_id) REFERENCES user (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE community_notification ADD CONSTRAINT FK_E045CE55E92F8F78 FOREIGN KEY (recipient_id) REFERENCES user (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE community_notification ADD CONSTRAINT FK_E045CE5510DAF24A FOREIGN KEY (actor_id) REFERENCES user (id) ON DELETE CASCADE');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE bridge_task_like DROP FOREIGN KEY FK_1CCEEA738A45ACDF');
        $this->addSql('ALTER TABLE bridge_task_like DROP FOREIGN KEY FK_1CCEEA73A76ED395');
        $this->addSql('ALTER TABLE community_notification DROP FOREIGN KEY FK_E045CE55E92F8F78');
        $this->addSql('ALTER TABLE community_notification DROP FOREIGN KEY FK_E045CE5510DAF24A');
        $this->addSql('DROP TABLE bridge_task_like');
        $this->addSql('DROP TABLE community_notification');
    }
}
