<?php

declare(strict_types=1);

namespace Application\Migrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20220125114839 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE api_app ADD shop_id INT DEFAULT NULL');
        $this->addSql('ALTER TABLE api_app ALTER store_id DROP NOT NULL');
        $this->addSql('ALTER TABLE api_app ADD CONSTRAINT FK_8AEC36FB4D16C4DD FOREIGN KEY (shop_id) REFERENCES restaurant (id) NOT DEFERRABLE INITIALLY IMMEDIATE');
        $this->addSql('CREATE INDEX IDX_8AEC36FB4D16C4DD ON api_app (shop_id)');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE api_app DROP CONSTRAINT FK_8AEC36FB4D16C4DD');
        $this->addSql('DROP INDEX IDX_8AEC36FB4D16C4DD');
        $this->addSql('ALTER TABLE api_app DROP shop_id');
        $this->addSql('ALTER TABLE api_app ALTER store_id SET NOT NULL');
    }
}
