<?php declare(strict_types = 1);

namespace Application\Migrations;

use Doctrine\Migrations\AbstractMigration;
use Doctrine\DBAL\Schema\Schema;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
class Version20180517113944 extends AbstractMigration
{
    public function up(Schema $schema) : void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->abortIf($this->connection->getDatabasePlatform()->getName() !== 'postgresql', 'Migration can only be executed safely on \'postgresql\'.');

        $this->addSql('ALTER TABLE menu_section DROP CONSTRAINT fk_a5a86751ccd7e912');
        $this->addSql('ALTER TABLE restaurant DROP CONSTRAINT fk_eb95123f12b2df0a');
        $this->addSql('ALTER TABLE modifier DROP CONSTRAINT fk_abbfd9fdc2e539a');
        $this->addSql('ALTER TABLE menu_item DROP CONSTRAINT fk_d754d550727aca70');
        $this->addSql('ALTER TABLE menu_item_modifier DROP CONSTRAINT fk_617144a09ab44fe0');

        $this->addSql('DROP SEQUENCE menu_category_id_seq CASCADE');
        $this->addSql('DROP SEQUENCE menu_item_id_seq CASCADE');
        $this->addSql('DROP SEQUENCE menu_item_modifier_id_seq CASCADE');
        $this->addSql('DROP SEQUENCE menu_section_id_seq CASCADE');
        $this->addSql('DROP SEQUENCE modifier_id_seq CASCADE');
        $this->addSql('DROP SEQUENCE menu_id_seq CASCADE');

        $this->addSql('DROP TABLE menu_category');
        $this->addSql('DROP TABLE menu');
        $this->addSql('DROP TABLE menu_item_modifier');
        $this->addSql('DROP TABLE menu_section');
        $this->addSql('DROP TABLE modifier');
        $this->addSql('DROP TABLE menu_item');
        $this->addSql('DROP INDEX uniq_eb95123f12b2df0a');

        $this->addSql('ALTER TABLE restaurant DROP legacy_menu_id');
    }

    public function down(Schema $schema) : void
    {

    }
}
