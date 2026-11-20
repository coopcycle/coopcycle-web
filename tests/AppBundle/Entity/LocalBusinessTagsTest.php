<?php

namespace Tests\AppBundle\Entity;

use ACSEO\TypesenseBundle\Manager\CollectionManager;
use AppBundle\Entity\LocalBusiness;
use AppBundle\Fixtures\DatabasePurger;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Symfony\Component\Serializer\Normalizer\NormalizerInterface;
use Typesense\Exceptions\ObjectNotFound;

/**
 * The tags of a restaurant are applied to the tasks of its orders,
 * they are meant for the dispatchers, not for the customers.
 */
class LocalBusinessTagsTest extends KernelTestCase
{
    private EntityManagerInterface $entityManager;

    protected function setUp(): void
    {
        parent::setUp();

        self::bootKernel();

        $container = self::getContainer();

        $dbPurger = $container->get(DatabasePurger::class);
        $dbPurger->purge();
        $dbPurger->resetSequences();

        $collectionManager = $container->get(CollectionManager::class);
        foreach (array_keys($collectionManager->getCollectionDefinitions()) as $name) {
            try {
                $collectionManager->deleteCollection($name);
            } catch (ObjectNotFound $e) {}

            $collectionManager->createCollection($name);
        }

        $container->get('fidry_alice_data_fixtures.loader.doctrine')->load([
            __DIR__.'/../../../fixtures/ORM/sylius_channels.yml',
            __DIR__.'/../../../fixtures/ORM/sylius_locales.yml',
            __DIR__.'/../../../fixtures/ORM/tags.yml',
            __DIR__.'/../../../fixtures/ORM/products.yml',
            __DIR__.'/../../../fixtures/ORM/restaurants.yml',
        ]);

        $this->entityManager = $container->get(EntityManagerInterface::class);
    }

    private function findRestaurant(): LocalBusiness
    {
        return $this->entityManager
            ->getRepository(LocalBusiness::class)
            ->findOneBy(['name' => 'Nodaiwa']);
    }

    private function tagRestaurant(array $tags): void
    {
        $restaurant = $this->findRestaurant();
        $restaurant->setTags($tags);

        $this->entityManager->flush();
        $this->entityManager->clear();
    }

    public function testTagsArePersisted()
    {
        $this->tagRestaurant(['fragile', 'perishable']);

        $this->assertEqualsCanonicalizing(['fragile', 'perishable'], $this->findRestaurant()->getTags());

        $this->tagRestaurant(['perishable']);

        $this->assertEquals(['perishable'], $this->findRestaurant()->getTags());
    }

    public function testTagsAreNotSerialized()
    {
        $this->tagRestaurant(['fragile']);

        $data = self::getContainer()->get(NormalizerInterface::class)
            ->normalize($this->findRestaurant(), 'json', ['groups' => ['restaurant', 'order']]);

        $this->assertSame('Nodaiwa', $data['name']);
        $this->assertArrayNotHasKey('tags', $data);
    }
}
