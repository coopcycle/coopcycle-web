<?php

namespace Application\Migrations;

use Doctrine\Migrations\AbstractMigration;
use Doctrine\DBAL\Schema\Schema;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
class Version20171130230406 extends AbstractMigration
{
    /**
     * @param Schema $schema
     */
    public function up(Schema $schema) : void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->abortIf($this->connection->getDatabasePlatform()->getName() !== 'postgresql', 'Migration can only be executed safely on \'postgresql\'.');

        $this->addSql('CREATE TABLE sylius_tax_category (id SERIAL NOT NULL, code VARCHAR(255) NOT NULL, name VARCHAR(255) NOT NULL, description TEXT DEFAULT NULL, created_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL, updated_at TIMESTAMP(0) WITHOUT TIME ZONE DEFAULT NULL, PRIMARY KEY(id))');
        $this->addSql('CREATE UNIQUE INDEX UNIQ_221EB0BE77153098 ON sylius_tax_category (code)');
        $this->addSql('CREATE TABLE sylius_tax_rate (id SERIAL NOT NULL, category_id INT NOT NULL, code VARCHAR(255) NOT NULL, name VARCHAR(255) NOT NULL, amount NUMERIC(10, 5) NOT NULL, included_in_price BOOLEAN NOT NULL, calculator VARCHAR(255) NOT NULL, created_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL, updated_at TIMESTAMP(0) WITHOUT TIME ZONE DEFAULT NULL, PRIMARY KEY(id))');
        $this->addSql('CREATE UNIQUE INDEX UNIQ_3CD86B2E77153098 ON sylius_tax_rate (code)');
        $this->addSql('CREATE INDEX IDX_3CD86B2E12469DE2 ON sylius_tax_rate (category_id)');
        $this->addSql('ALTER TABLE sylius_tax_rate ADD CONSTRAINT FK_3CD86B2E12469DE2 FOREIGN KEY (category_id) REFERENCES sylius_tax_category (id) NOT DEFERRABLE INITIALLY IMMEDIATE');

        $tax_categories = [
            'tva_conso_immediate' => 'TVA consommation immédiate',
            'tva_conso_differee' => 'TVA consommation différée',
        ];

        foreach ($tax_categories as $code => $name) {
            $this->addSql("INSERT INTO sylius_tax_category (code, name, created_at, updated_at) VALUES (:code, :name, CURRENT_TIMESTAMP, CURRENT_TIMESTAMP)", [
                'code' => $code,
                'name' => $name
            ]);
        }

        $tax_rates = [
            'tva_10' => [
                'category' => 'tva_conso_immediate',
                'name' => 'Taux 10%',
                'amount' => 10
            ],
            'tva_5_5' => [
                'category' => 'tva_conso_differee',
                'name' => 'Taux 5.5%',
                'amount' => 5.5
            ]
        ];

        foreach ($tax_rates as $code => $params) {
            $this->addSql("INSERT INTO sylius_tax_rate (category_id, code, name, amount, included_in_price, calculator, created_at, updated_at) SELECT sylius_tax_category.id, :code, :name, :amount, TRUE, 'default', CURRENT_TIMESTAMP, CURRENT_TIMESTAMP FROM sylius_tax_category WHERE sylius_tax_category.code = :category", [
                'code' => $code,
                'name' => $params['name'],
                'amount' => $params['amount'],
                'category' => $params['category'],
            ]);
        }
    }

    /**
     * @param Schema $schema
     */
    public function down(Schema $schema) : void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->abortIf($this->connection->getDatabasePlatform()->getName() !== 'postgresql', 'Migration can only be executed safely on \'postgresql\'.');

        $this->addSql('ALTER TABLE sylius_tax_rate DROP CONSTRAINT FK_3CD86B2E12469DE2');
        $this->addSql('DROP TABLE sylius_tax_category');
        $this->addSql('DROP TABLE sylius_tax_rate');
    }
}
