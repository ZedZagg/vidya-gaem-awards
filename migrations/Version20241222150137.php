<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20241222150137 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE user_nominations ADD original_group_id INT DEFAULT NULL');
        $this->addSql('ALTER TABLE user_nominations ADD CONSTRAINT FK_A1FEB8AADD0B3E56 FOREIGN KEY (original_group_id) REFERENCES user_nomination_groups (id)');
        $this->addSql('CREATE INDEX IDX_A1FEB8AADD0B3E56 ON user_nominations (original_group_id)');
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE user_nominations DROP FOREIGN KEY FK_A1FEB8AADD0B3E56');
        $this->addSql('DROP INDEX IDX_A1FEB8AADD0B3E56 ON user_nominations');
        $this->addSql('ALTER TABLE user_nominations DROP original_group_id');
    }
}
