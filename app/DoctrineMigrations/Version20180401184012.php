<?php declare(strict_types = 1);

namespace Application\Migrations;

use Doctrine\Migrations\AbstractMigration;
use Doctrine\DBAL\Schema\Schema;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
class Version20180401184012 extends AbstractMigration
{
    public function up(Schema $schema) : void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->abortIf($this->connection->getDatabasePlatform()->getName() !== 'postgresql', 'Migration can only be executed safely on \'postgresql\'.');

        $this->addSql('ALTER TABLE delivery ADD sylius_order_id INT DEFAULT NULL');
        $this->addSql('ALTER TABLE delivery ADD CONSTRAINT FK_3781EC10722436D7 FOREIGN KEY (sylius_order_id) REFERENCES sylius_order (id) NOT DEFERRABLE INITIALLY IMMEDIATE');
        $this->addSql('CREATE UNIQUE INDEX UNIQ_3781EC10722436D7 ON delivery (sylius_order_id)');

        $this->addSql('ALTER TABLE delivery ALTER tax_category_id DROP NOT NULL');
        $this->addSql('ALTER TABLE delivery ALTER total_excluding_tax DROP NOT NULL');
        $this->addSql('ALTER TABLE delivery ALTER total_tax DROP NOT NULL');
        $this->addSql('ALTER TABLE delivery ALTER total_including_tax DROP NOT NULL');
        $this->addSql('ALTER TABLE delivery ALTER date DROP NOT NULL');
        $this->addSql('ALTER TABLE delivery ALTER price DROP NOT NULL');
    }

    public function down(Schema $schema) : void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->abortIf($this->connection->getDatabasePlatform()->getName() !== 'postgresql', 'Migration can only be executed safely on \'postgresql\'.');

        $this->addSql('ALTER TABLE delivery DROP CONSTRAINT FK_3781EC10722436D7');
        $this->addSql('DROP INDEX UNIQ_3781EC10722436D7');
        $this->addSql('ALTER TABLE delivery DROP sylius_order_id');

        $this->addSql('ALTER TABLE delivery ALTER tax_category_id SET NOT NULL');
        $this->addSql('ALTER TABLE delivery ALTER date SET NOT NULL');
        $this->addSql('ALTER TABLE delivery ALTER price SET NOT NULL');
        $this->addSql('ALTER TABLE delivery ALTER total_excluding_tax SET NOT NULL');
        $this->addSql('ALTER TABLE delivery ALTER total_tax SET NOT NULL');
        $this->addSql('ALTER TABLE delivery ALTER total_including_tax SET NOT NULL');
    }
}
