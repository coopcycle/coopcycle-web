<?php

declare(strict_types=1);

namespace Application\Migrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20191212172101 extends AbstractMigration
{
    public function getDescription() : string
    {
        return '';
    }

    public function up(Schema $schema) : void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->abortIf($this->connection->getDatabasePlatform()->getName() !== 'postgresql', 'Migration can only be executed safely on \'postgresql\'.');

        // Position field

        $this->addSql('ALTER TABLE sylius_product_options ADD position INT DEFAULT NULL');

        $stmt = $this->connection->prepare('SELECT p.id AS product_id, po.id as option_id, po.additional AS is_additional, po.position FROM sylius_product_options pos JOIN sylius_product p ON p.id = pos.product_id JOIN sylius_product_option po ON po.id = pos.option_id ORDER by p.id ASC');

        $result = $stmt->execute();

        $products = [];
        while ($product = $result->fetchAssociative()) {
            $products[$product['product_id']][] = $product;
        }

        foreach ($products as $options) {
            // Mandatory options first, then sort by priority
            uasort($options, function ($a, $b) {
                if ($a['is_additional'] === $b['is_additional']) {
                    return $a['position'] - $b['position'];
                }
                return $a['is_additional'] - $b['is_additional'];
            });
            foreach (array_values($options) as $position => $option) {
                $this->addSql('UPDATE sylius_product_options SET position = :position WHERE product_id = :product_id AND option_id = :option_id', [
                    'position' => $position,
                    'product_id' => $option['product_id'],
                    'option_id' => $option['option_id'],
                ]);
            }
        }

        $this->addSql('ALTER TABLE sylius_product_options ALTER COLUMN position SET NOT NULL');

        // Other fields

        $this->addSql('ALTER TABLE sylius_product_options DROP CONSTRAINT sylius_product_options_pkey');
        $this->addSql('CREATE UNIQUE INDEX sylius_product_option_unique ON sylius_product_options (product_id, option_id)');

        $this->addSql('ALTER TABLE sylius_product_options ADD id SERIAL NOT NULL');
        $this->addSql('ALTER TABLE sylius_product_options ADD PRIMARY KEY (id)');
    }

    public function down(Schema $schema) : void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->abortIf($this->connection->getDatabasePlatform()->getName() !== 'postgresql', 'Migration can only be executed safely on \'postgresql\'.');

        $this->addSql('ALTER TABLE sylius_product_options DROP position');

        $this->addSql('ALTER TABLE sylius_product_options DROP CONSTRAINT sylius_product_options_pkey');
        $this->addSql('ALTER TABLE sylius_product_options DROP id');

        $this->addSql('DROP INDEX sylius_product_option_unique');
        $this->addSql('ALTER TABLE sylius_product_options ADD PRIMARY KEY (product_id, option_id)');
    }
}
