<?php declare(strict_types=1);

namespace Application\Migrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20181117163440 extends AbstractMigration
{
    const PRODUCT_CODE = 'CPCCL-ODDLVR';

    public function up(Schema $schema) : void
    {
        // this up() migration is auto-generated, please modify it to your needs

        $stmt['product'] = $this->connection->prepare('SELECT p.id, pt.name FROM sylius_product p JOIN sylius_product_translation pt ON p.id = pt.translatable_id WHERE p.code != :product_code');
        $stmt['variant'] = $this->connection->prepare('SELECT pvt.id AS translation_id, pv.id, pvt.name FROM sylius_product_variant pv JOIN sylius_product_variant_translation pvt ON pv.id = pvt.translatable_id WHERE pv.product_id = :product_id');

        $productCode = self::PRODUCT_CODE;
        $stmt['product']->bindParam('product_code', $productCode);

        $stmt['product']->execute();
        while ($product = $stmt['product']->fetch()) {

            $stmt['variant']->bindParam('product_id', $product['id']);
            $stmt['variant']->execute();
            while ($variant = $stmt['variant']->fetch()) {

                if ($variant['name'] !== $product['name']) {
                    $this->addSql('UPDATE sylius_product_variant_translation SET name = :product_name WHERE id = :translation_id', [
                        'product_name' => $product['name'],
                        'translation_id' => $variant['translation_id'],
                    ]);
                }
            }
        }
    }

    public function down(Schema $schema) : void
    {
        // this down() migration is auto-generated, please modify it to your needs

    }
}
