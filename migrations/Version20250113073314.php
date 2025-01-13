<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20250113073314 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql('CREATE TABLE lootbox_item_files (lootbox_item_id INT NOT NULL, file_id INT NOT NULL, INDEX IDX_50EDE64664B41C5C (lootbox_item_id), INDEX IDX_50EDE64693CB796C (file_id), PRIMARY KEY(lootbox_item_id, file_id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB');
        $this->addSql('ALTER TABLE lootbox_item_files ADD CONSTRAINT FK_50EDE64664B41C5C FOREIGN KEY (lootbox_item_id) REFERENCES lootbox_items (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE lootbox_item_files ADD CONSTRAINT FK_50EDE64693CB796C FOREIGN KEY (file_id) REFERENCES files (id) ON DELETE CASCADE');
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE lootbox_item_files DROP FOREIGN KEY FK_50EDE64664B41C5C');
        $this->addSql('ALTER TABLE lootbox_item_files DROP FOREIGN KEY FK_50EDE64693CB796C');
        $this->addSql('DROP TABLE lootbox_item_files');
    }
}
