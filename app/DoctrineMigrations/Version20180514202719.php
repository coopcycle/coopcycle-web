<?php declare(strict_types = 1);

namespace Application\Migrations;

use Doctrine\Migrations\AbstractMigration;
use Doctrine\DBAL\Schema\Schema;
use Ramsey\Uuid\Uuid;
use Symfony\Component\DependencyInjection\ContainerAwareInterface;
use Symfony\Component\DependencyInjection\ContainerAwareTrait;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
class Version20180514202719 extends AbstractMigration implements ContainerAwareInterface
{
    use ContainerAwareTrait;

    private $locale;

    private function createMenu(array $menu)
    {
        // Build nested set values
        $left = 2;
        foreach ($menu['children'] as $key => $child) {
            $menu['children'][$key]['left'] = $left;
            $menu['children'][$key]['right'] = ($left + 1);
            $left += 2;
        }

        $menu['left'] = 1;
        $menu['right'] = $left;

        $this->addSql('INSERT INTO sylius_taxon (code, tree_left, tree_right, tree_level, position) VALUES (:code, :tree_left, :tree_right, 0, 1)', [
            'code' => $menu['code'],
            'tree_left' => $menu['left'],
            'tree_right' => $menu['right'],
        ]);

        $this->addSql('UPDATE sylius_taxon SET tree_root = id WHERE code = :code', [
            'code' => $menu['code'],
        ]);

        $this->addSql('INSERT INTO sylius_taxon_translation (translatable_id, name, slug, locale) SELECT id, :name, :code, :locale FROM sylius_taxon WHERE code = :code', [
            'code' => $menu['code'],
            'name' => 'Default',
            'locale' => $this->locale,
        ]);

        $i = 1;
        foreach ($menu['children'] as $child) {

            $this->addSql('INSERT INTO sylius_taxon (tree_root, parent_id, code, tree_left, tree_right, tree_level, position) SELECT id, id, :code, :tree_left, :tree_right, 1, :position FROM sylius_taxon WHERE code = :parent_code', [
                'code' => $child['code'],
                'tree_left' => $child['left'],
                'tree_right' => $child['right'],
                'position' => $i++,
                'parent_code' => $menu['code'],
            ]);
            $this->addSql('INSERT INTO sylius_taxon_translation (translatable_id, name, slug, locale) SELECT id, :name, :code, :locale FROM sylius_taxon WHERE code = :code', [
                'code' => $child['code'],
                'name' => $child['name'],
                'locale' => $this->locale,
            ]);

            $j = 1;
            foreach ($child['products'] as $productCode) {
                $this->addSql('INSERT INTO sylius_product_taxon (product_id, taxon_id, position) SELECT sylius_product.id, sylius_taxon.id, :position FROM sylius_product, sylius_taxon WHERE sylius_product.code = :product_code AND sylius_taxon.code = :taxon_code', [
                    'product_code' => $productCode,
                    'taxon_code' => $child['code'],
                    'position' => $j++,
                ]);
            }
        }

        $this->addSql('INSERT INTO restaurant_taxon (restaurant_id, taxon_id) SELECT :restaurant_id, id FROM sylius_taxon WHERE code = :code', [
            'restaurant_id' => $menu['restaurant_id'],
            'code' => $menu['code'],
        ]);
    }

    public function up(Schema $schema) : void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->abortIf($this->connection->getDatabasePlatform()->getName() !== 'postgresql', 'Migration can only be executed safely on \'postgresql\'.');

        $this->locale = $this->container->getParameter('coopcycle.locale');

        $stmt = [];
        $stmt['menu'] =
            $this->connection->prepare('SELECT menu.id, restaurant.id AS restaurant_id FROM menu JOIN restaurant ON menu.id = restaurant.menu_id');
        $stmt['menu_section'] =
            $this->connection->prepare('SELECT * FROM menu_section WHERE menu_id = :menu_id');
        $stmt['menu_item'] =
            $this->connection->prepare('SELECT * FROM menu_item WHERE parent_id = :menu_section_id ORDER BY position ASC');

        $stmt['menu']->execute();
        while ($menu = $stmt['menu']->fetch()) {

            $stmt['menu_section']->bindParam('menu_id', $menu['id']);
            $stmt['menu_section']->execute();

            $menu['code'] = Uuid::uuid4()->toString();
            $menu['children'] = [];

            while ($menuSection = $stmt['menu_section']->fetch()) {

                if (empty($menuSection['name'])) {
                    $menuSection['name'] = '???';
                }

                $menuSection['code'] = Uuid::uuid4()->toString();

                $stmt['menu_item']->bindParam('menu_section_id', $menuSection['id']);
                $stmt['menu_item']->execute();

                $menuSection['products'] = [];
                while ($menuItem = $stmt['menu_item']->fetch()) {
                    $productCode = sprintf('CPCCL-FDTCH-%d', $menuItem['id']);
                    $menuSection['products'][] = $productCode;
                }

                $menu['children'][] = $menuSection;
            }

            $this->createMenu($menu);
        }

        $this->addSql('ALTER TABLE restaurant DROP CONSTRAINT fk_eb95123fccd7e912');
        $this->addSql('DROP INDEX uniq_eb95123fccd7e912');
        $this->addSql('ALTER TABLE restaurant RENAME COLUMN menu_id TO legacy_menu_id');
        $this->addSql('ALTER TABLE restaurant ADD CONSTRAINT FK_EB95123F12B2DF0A FOREIGN KEY (legacy_menu_id) REFERENCES menu (id) NOT DEFERRABLE INITIALLY IMMEDIATE');
        $this->addSql('CREATE UNIQUE INDEX UNIQ_EB95123F12B2DF0A ON restaurant (legacy_menu_id)');

        // Indexes on restaurant_product_option
        $this->addSql('ALTER INDEX idx_c713f7d5b1e7706e RENAME TO IDX_CB35112EB1E7706E');
        $this->addSql('ALTER INDEX idx_c713f7d5a7c41d6f RENAME TO IDX_CB35112EA7C41D6F');
    }

    public function down(Schema $schema) : void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->abortIf($this->connection->getDatabasePlatform()->getName() !== 'postgresql', 'Migration can only be executed safely on \'postgresql\'.');

        $this->addSql('DELETE FROM restaurant_taxon');
        $this->addSql('DELETE FROM sylius_taxon');

        $this->addSql('ALTER TABLE restaurant DROP CONSTRAINT FK_EB95123F12B2DF0A');
        $this->addSql('DROP INDEX UNIQ_EB95123F12B2DF0A');
        $this->addSql('ALTER TABLE restaurant RENAME COLUMN legacy_menu_id TO menu_id');
        $this->addSql('ALTER TABLE restaurant ADD CONSTRAINT fk_eb95123fccd7e912 FOREIGN KEY (menu_id) REFERENCES menu (id) NOT DEFERRABLE INITIALLY IMMEDIATE');
        $this->addSql('CREATE UNIQUE INDEX uniq_eb95123fccd7e912 ON restaurant (menu_id)');

        // Indexes on restaurant_product_option
        $this->addSql('ALTER INDEX idx_cb35112ea7c41d6f RENAME TO idx_c713f7d5a7c41d6f');
        $this->addSql('ALTER INDEX idx_cb35112eb1e7706e RENAME TO idx_c713f7d5b1e7706e');
    }
}
