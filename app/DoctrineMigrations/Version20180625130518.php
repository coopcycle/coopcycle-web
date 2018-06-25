<?php declare(strict_types = 1);

namespace Application\Migrations;

use Doctrine\DBAL\Migrations\AbstractMigration;
use Doctrine\DBAL\Schema\Schema;
use Ramsey\Uuid\Uuid;
use Symfony\Component\DependencyInjection\ContainerAwareInterface;
use Symfony\Component\DependencyInjection\ContainerAwareTrait;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
class Version20180625130518 extends AbstractMigration implements ContainerAwareInterface
{

    use ContainerAwareTrait;

    public function up(Schema $schema)
    {
        // this up() migration is auto-generated, please modify it to your needs
        $stmt = $this->connection->prepare("SELECT id FROM store");
        $stmt->execute();

        while ($store = $stmt->fetch()) {

            $this->locale = $this->container->getParameter('coopcycle.locale');
            $this->translator = $this->getContainer()->get('translator');

            $uuid = Uuid::uuid4()->toString();

            $this->addSql(
                'INSERT INTO sylius_taxon (tree_root, code, tree_left, tree_right, tree_level, position) VALUES (1, :uuid, 1, 2, 0, 1)',
                ['uuid' => $uuid]
            );

            $this->addSql('UPDATE sylius_taxon SET tree_root = id WHERE code = :code', [
                'code' => $uuid,
            ]);

            $this->addSql('INSERT INTO sylius_taxon_translation (translatable_id, name, slug, locale) SELECT id, :name, :code, :locale FROM sylius_taxon WHERE code = :code', [
                'code' => $uuid,
                'name' => $this->translator->trans('stores.catalog'),
                'locale' => $this->locale,
            ]);

            $this->addSql('UPDATE sylius_taxon SET tree_root = id WHERE code = :code', [
                'code' => $uuid,
            ]);

            $this->addSql(
                "UPDATE store SET active_menu_taxon_id = (SELECT id FROM sylius_taxon WHERE code = :code) WHERE id = :id",
                ['id' => $store['id'], 'code' => $uuid]);
        }

    }

    public function down(Schema $schema)
    {
        // this down() migration is auto-generated, please modify it to your needs

    }
}

