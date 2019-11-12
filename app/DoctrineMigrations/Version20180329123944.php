<?php declare(strict_types = 1);

namespace Application\Migrations;

use AppBundle\Entity\Restaurant;
use Doctrine\Migrations\AbstractMigration;
use Doctrine\DBAL\Schema\Schema;
use Symfony\Component\DependencyInjection\ContainerAwareInterface;
use Symfony\Component\DependencyInjection\ContainerAwareTrait;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
class Version20180329123944 extends AbstractMigration implements ContainerAwareInterface
{
    use ContainerAwareTrait;

    const BATCH_SIZE = 20;

    public function up(Schema $schema) : void
    {
        // this up() migration is auto-generated, please modify it to your needs

        $restaurants = $this->container->get('doctrine')
            ->getRepository(Restaurant::class)
            ->findAll();

        $productFactory = $this->container->get('sylius.factory.product');
        $productVariantFactory = $this->container->get('sylius.factory.product_variant');
        $slugify = $this->container->get('slugify');

        $i = 0;
        foreach ($restaurants as $restaurant) {
            foreach ($restaurant->getMenu()->getAllItems() as $menuItem) {

                $product = $productFactory->createForMenuItem($menuItem);
                $productVariant = $productVariantFactory->createForMenuItem($menuItem);

                $productVariant->setProduct($product);

                $this->container->get('sylius.manager.product')->persist($product);
                $this->write(sprintf('<info>Created product %s</info>', $product->getCode()));

                $this->container->get('sylius.manager.product_variant')->persist($productVariant);
                $this->write(sprintf('<info>  Created product variant %s</info>', $productVariant->getCode()));

                if ((++$i % self::BATCH_SIZE) === 0) {

                    $this->container->get('sylius.manager.product')->flush();
                    $this->container->get('sylius.manager.product_variant')->flush();

                    $this->write('<info>Flushing data…</info>');
                }
            }
        }

        $this->container->get('sylius.manager.product')->flush();
        $this->container->get('sylius.manager.product_variant')->flush();
    }

    public function down(Schema $schema) : void
    {
        // this down() migration is auto-generated, please modify it to your needs

        $this->addSql('DELETE FROM sylius_product_variant WHERE code like :pattern', [
            'pattern' => 'CPCCL-FDTCH-%'
        ]);
        $this->addSql('DELETE FROM sylius_product WHERE code like :pattern', [
            'pattern' => 'CPCCL-FDTCH-%'
        ]);
    }
}
