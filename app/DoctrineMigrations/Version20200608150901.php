<?php

declare(strict_types=1);

namespace Application\Migrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;
use Symfony\Component\DependencyInjection\ContainerAwareInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\DependencyInjection\ContainerAwareTrait;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20200608150901 extends AbstractMigration implements ContainerAwareInterface
{
    use ContainerAwareTrait;

    public function getDescription() : string
    {
        return '';
    }

    private function getDefaultTaxCategory()
    {
        $stmt =
            $this->connection->prepare('SELECT c.code, r.code AS rate_code, r.amount FROM sylius_tax_category c JOIN sylius_tax_rate r ON c.id = r.category_id WHERE c.code = ('.
                'SELECT value FROM craue_config_setting WHERE name = \'default_tax_category\''.
            ')');

        $stmt->execute();

        $defaultTaxCategory = $stmt->fetch();

        if (!$defaultTaxCategory) {

            return null;
        }

        return $defaultTaxCategory;
    }

    private function getSubjectToVatSetting()
    {
        $stmt =
            $this->connection->prepare('SELECT value FROM craue_config_setting WHERE name = \'subject_to_vat\'');

        $stmt->execute();

        $subjectToVat = $stmt->fetch();

        if (!$subjectToVat) {

            return true;
        }

        return (bool) $subjectToVat;
    }

    private function getTaxRateCode()
    {
        $state = $this->container->getParameter('region_iso');
        $subjectToVat = $this->getSubjectToVatSetting();

        return strtoupper(sprintf('%s_SERVICE_%s', $state, $subjectToVat ? 'STANDARD' : 'ZERO'));
    }

    public function up(Schema $schema) : void
    {
        $defaultTaxCategory = $this->getDefaultTaxCategory();
        if (!$defaultTaxCategory) {
            return;
        }

        $this->addSql('UPDATE sylius_adjustment SET origin_code = :new_origin_code WHERE type = \'tax\' AND order_id IS NOT NULL AND origin_code = :old_origin_code', [
            'new_origin_code' => $this->getTaxRateCode(),
            'old_origin_code' => $defaultTaxCategory['rate_code'],
        ]);

        $this->addSql('UPDATE sylius_adjustment SET origin_code = :new_origin_code WHERE type = \'tax\' AND order_item_id IN ('.
            'SELECT i.id FROM sylius_order_item i JOIN sylius_product_variant v ON i.variant_id = v.id JOIN sylius_product
p ON p.id = v.product_id WHERE p.code = \'CPCCL-ODDLVR\''
            .')', [
            'new_origin_code' => $this->getTaxRateCode(),
        ]);
    }

    public function down(Schema $schema) : void
    {
        $defaultTaxCategory = $this->getDefaultTaxCategory();
        if (!$defaultTaxCategory) {
            return;
        }

        $this->addSql('UPDATE sylius_adjustment SET origin_code = :new_origin_code WHERE type = \'tax\' AND order_id IS NOT NULL AND origin_code = :old_origin_code', [
            'new_origin_code' => $defaultTaxCategory['rate_code'],
            'old_origin_code' => $this->getTaxRateCode(),
        ]);

        $this->addSql('UPDATE sylius_adjustment SET origin_code = :new_origin_code WHERE type = \'tax\' AND order_item_id IN ('.
            'SELECT i.id FROM sylius_order_item i JOIN sylius_product_variant v ON i.variant_id = v.id JOIN sylius_product
p ON p.id = v.product_id WHERE p.code = \'CPCCL-ODDLVR\''
            .')', [
            'new_origin_code' => $defaultTaxCategory['rate_code'],
        ]);
    }
}
