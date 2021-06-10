<?php

declare(strict_types=1);

namespace Application\Migrations;

use Doctrine\DBAL\Connection;
use Doctrine\DBAL\ParameterType;
use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;
use Symfony\Component\DependencyInjection\ContainerAwareInterface;
use Symfony\Component\DependencyInjection\ContainerAwareTrait;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20210610121312 extends AbstractMigration implements ContainerAwareInterface
{
    use ContainerAwareTrait;

    public function getDescription(): string
    {
        return 'Cleanup data after https://github.com/coopcycle/coopcycle-web/pull/2406';
    }

    public function up(Schema $schema): void
    {
        $translator = $this->container->get('translator');

        $taxCategories = ['FOOD', 'JEWELRY'];
        $country = 'ca-bc';

        $sql = 'SELECT r.id, r.category_id, r.code, r.name FROM sylius_tax_rate r JOIN sylius_tax_category c ON r.category_id = c.id WHERE r.country = :country AND c.code NOT IN (:codes) AND r.amount = (SELECT amount FROM sylius_tax_rate r JOIN sylius_tax_category c ON r.category_id = c.id WHERE c.code = :code)';

        foreach ($taxCategories as $taxCategoryCode) {

            $oldTaxCategoryQuery = $this->connection->executeQuery(
                'SELECT id FROM sylius_tax_category WHERE code = :code',
                [
                    'code' => $taxCategoryCode,
                ]
            );

            $oldTaxCategoryId = $oldTaxCategoryQuery->fetchColumn(0);

            $newTaxRateQuery = $this->connection->executeQuery(
                $sql,
                [
                    'country' => $country,
                    'codes'   => $taxCategories,
                    'code'    => $taxCategoryCode,
                ], [
                    'codes' => Connection::PARAM_STR_ARRAY
                ]
            );

            if ($newTaxRateQuery->rowCount() > 1) {
                // TODO Show error
                continue;
            }

            $newTaxRate = $newTaxRateQuery->fetch();

            $this->addSql('UPDATE sylius_product_variant SET tax_category_id = :new_category_id WHERE tax_category_id = :old_category_id', [
                'new_category_id' => $newTaxRate['category_id'],
                'old_category_id' => $oldTaxCategoryId,
            ]);

            $oldTaxRatesQuery = $this->connection->executeQuery(
                'SELECT r.* FROM sylius_tax_rate r JOIN sylius_tax_category c ON r.category_id = c.id WHERE r.country = :country AND c.code = :code',
                [
                    'country' => $country,
                    'code' => $taxCategoryCode,
                ]
            );

            while ($oldTaxRate = $oldTaxRatesQuery->fetch()) {

                $this->addSql('UPDATE sylius_adjustment SET origin_code = :new_origin_code, label = :label WHERE type = \'tax\' AND origin_code = :old_origin_code', [
                    'new_origin_code' => $newTaxRate['code'],
                    'label' => $translator->trans($newTaxRate['name'], [], 'taxation'),
                    'old_origin_code' => $oldTaxRate['code'],
                ]);

                $this->addSql('DELETE FROM sylius_tax_rate WHERE code = :code', [
                    'code' => $oldTaxRate['code'],
                ]);
            }

            $this->addSql('DELETE FROM sylius_tax_category WHERE id = :old_category_id', [
                'old_category_id' => $oldTaxCategoryId,
            ]);
        }
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs

    }
}
