<?php

declare(strict_types=1);

namespace Application\Migrations;

use AppBundle\Entity\Package;
use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;
use Symfony\Component\DependencyInjection\ContainerAwareInterface;
use Symfony\Component\DependencyInjection\ContainerAwareTrait;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20220805151009 extends AbstractMigration implements ContainerAwareInterface
{
    use ContainerAwareTrait;

    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE package ADD slug VARCHAR(255)');
        $this->addSql('CREATE UNIQUE INDEX UNIQ_DE686795989D9B62 ON package (slug)');
    }

    public function postUp(Schema $schema): void
    {
        $em = $this->container->get('doctrine.orm.entity_manager');

        $packages = $this->container->get('doctrine')
            ->getRepository(Package::class)
            ->findAll();

        foreach ($packages as $package) {
            $package->setSlug($package->getName());
            $em->persist($package);
        }

        $em->flush();
    }

    public function down(Schema $schema): void
    {
        $this->addSql('DROP INDEX UNIQ_DE686795989D9B62');
        $this->addSql('ALTER TABLE package DROP slug');
    }
}
