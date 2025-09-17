<?php

declare(strict_types=1);

namespace Application\Migrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20250705003629 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql(<<<'SQL'
            ALTER TABLE pricing_rule ADD product_option_value_id INT DEFAULT NULL
        SQL);
        $this->addSql(<<<'SQL'
            ALTER TABLE pricing_rule ADD CONSTRAINT FK_6DCEA672EBDCCF9B FOREIGN KEY (product_option_value_id) REFERENCES sylius_product_option_value (id) NOT DEFERRABLE INITIALLY IMMEDIATE
        SQL);
        $this->addSql(<<<'SQL'
            CREATE INDEX IDX_6DCEA672EBDCCF9B ON pricing_rule (product_option_value_id)
        SQL);
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql(<<<'SQL'
            ALTER TABLE pricing_rule DROP CONSTRAINT FK_6DCEA672EBDCCF9B
        SQL);
        $this->addSql(<<<'SQL'
            DROP INDEX IDX_6DCEA672EBDCCF9B
        SQL);
        $this->addSql(<<<'SQL'
            ALTER TABLE pricing_rule DROP product_option_value_id
        SQL);
    }
}
