<?php

declare(strict_types=1);

namespace Application\Migrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20250520125136 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Add promotion translations & tax rate start/end dates';
    }

    public function up(Schema $schema): void
    {
        $this->addSql(<<<'SQL'
            CREATE TABLE sylius_promotion_translation (id SERIAL NOT NULL, translatable_id INT NOT NULL, label VARCHAR(255) DEFAULT NULL, locale VARCHAR(255) NOT NULL, PRIMARY KEY(id))
        SQL);
        $this->addSql(<<<'SQL'
            CREATE INDEX IDX_3C7A76182C2AC5D3 ON sylius_promotion_translation (translatable_id)
        SQL);
        $this->addSql(<<<'SQL'
            CREATE UNIQUE INDEX sylius_promotion_translation_uniq_trans ON sylius_promotion_translation (translatable_id, locale)
        SQL);
        $this->addSql(<<<'SQL'
            ALTER TABLE sylius_promotion_translation ADD CONSTRAINT FK_3C7A76182C2AC5D3 FOREIGN KEY (translatable_id) REFERENCES sylius_promotion (id) ON DELETE CASCADE NOT DEFERRABLE INITIALLY IMMEDIATE
        SQL);

        $this->addSql(<<<'SQL'
            ALTER TABLE sylius_promotion ADD archived_at TIMESTAMP(0) WITHOUT TIME ZONE DEFAULT NULL
        SQL);

        $this->addSql(<<<'SQL'
            ALTER TABLE sylius_tax_rate ADD start_date TIMESTAMP(0) WITHOUT TIME ZONE DEFAULT NULL
        SQL);
        $this->addSql(<<<'SQL'
            ALTER TABLE sylius_tax_rate ADD end_date TIMESTAMP(0) WITHOUT TIME ZONE DEFAULT NULL
        SQL);

        // Use new columns
        $this->addSql(<<<'SQL'
            UPDATE sylius_tax_rate SET start_date = valid_from, end_date = valid_to
        SQL);
        $this->addSql(<<<'SQL'
            ALTER TABLE sylius_tax_rate DROP valid_from
        SQL);
        $this->addSql(<<<'SQL'
            ALTER TABLE sylius_tax_rate DROP valid_to
        SQL);

    }

    public function down(Schema $schema): void
    {
        $this->addSql(<<<'SQL'
            ALTER TABLE sylius_promotion_translation DROP CONSTRAINT FK_3C7A76182C2AC5D3
        SQL);
        $this->addSql(<<<'SQL'
            DROP TABLE sylius_promotion_translation
        SQL);

        $this->addSql(<<<'SQL'
            ALTER TABLE sylius_promotion DROP archived_at
        SQL);

        $this->addSql(<<<'SQL'
            ALTER TABLE sylius_tax_rate ADD valid_from TIMESTAMP(0) WITHOUT TIME ZONE DEFAULT NULL
        SQL);
        $this->addSql(<<<'SQL'
            ALTER TABLE sylius_tax_rate ADD valid_to TIMESTAMP(0) WITHOUT TIME ZONE DEFAULT NULL
        SQL);

        $this->addSql(<<<'SQL'
            UPDATE sylius_tax_rate SET valid_from = start_date, valid_to = end_date
        SQL);

        $this->addSql(<<<'SQL'
            ALTER TABLE sylius_tax_rate DROP start_date
        SQL);
        $this->addSql(<<<'SQL'
            ALTER TABLE sylius_tax_rate DROP end_date
        SQL);
    }
}
