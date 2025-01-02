<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20241222101941 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql('CREATE TABLE user_nomination_groups (id INT AUTO_INCREMENT NOT NULL, award_id VARCHAR(30) NOT NULL, name VARCHAR(255) NOT NULL, ignored TINYINT(1) NOT NULL, nominee_id INT DEFAULT NULL, merged_into_id INT DEFAULT NULL, UNIQUE INDEX UNIQ_DE434D06C6A2398 (nominee_id), INDEX IDX_DE434D06285E57BA (merged_into_id), INDEX IDX_DE434D063D5282CF (award_id), PRIMARY KEY(id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB');
        $this->addSql('ALTER TABLE user_nomination_groups ADD CONSTRAINT FK_DE434D06C6A2398 FOREIGN KEY (nominee_id) REFERENCES nominees (id)');
        $this->addSql('ALTER TABLE user_nomination_groups ADD CONSTRAINT FK_DE434D06285E57BA FOREIGN KEY (merged_into_id) REFERENCES user_nomination_groups (id)');
        $this->addSql('ALTER TABLE user_nomination_groups ADD CONSTRAINT FK_DE434D063D5282CF FOREIGN KEY (award_id) REFERENCES awards (id)');
        $this->addSql('ALTER TABLE user_nominations ADD nomination_group_id INT NOT NULL');
        $this->addSql('CREATE UNIQUE INDEX award_name ON user_nomination_groups (award_id, name)');
    }

    public function postUp(Schema $schema): void
    {
        $result = $this->connection->executeQuery('SELECT * FROM user_nominations ORDER BY awardID ASC, nomination ASC');

        $userNominationGroups = [];

        while ($row = $result->fetchAssociative()) {
            $award = $row['awardID'];
            $nomination = $row['nomination'];
            $nominationLowercase = iconv('UTF-8', 'ASCII//TRANSLIT', mb_strtolower($nomination));

            if (!isset($userNominationGroups[$award][$nominationLowercase])) {
                $this->connection->insert('user_nomination_groups', [
                    'award_id' => $award,
                    'name' => $nomination,
                    'ignored' => 0,
                ]);

                $userNominationGroups[$award][$nominationLowercase] = [
                    'id' => $this->connection->lastInsertId(),
                    'name' => $nomination,
                ];
            }

            $id = $userNominationGroups[$award][$nominationLowercase]['id'];
            $this->connection->update('user_nominations', ['nomination_group_id' => $id], ['awardID' => $award, 'nomination' => $nomination]);
        }
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('DROP INDEX award_name ON user_nomination_groups');
        $this->addSql('ALTER TABLE user_nomination_groups DROP FOREIGN KEY FK_DE434D06C6A2398');
        $this->addSql('ALTER TABLE user_nomination_groups DROP FOREIGN KEY FK_DE434D06285E57BA');
        $this->addSql('ALTER TABLE user_nomination_groups DROP FOREIGN KEY FK_DE434D063D5282CF');
        $this->addSql('DROP TABLE user_nomination_groups');
        $this->addSql('ALTER TABLE user_nominations DROP nomination_group_id');
    }
}
