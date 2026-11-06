<?php

namespace Tests\AppBundle\Controller;

use ACSEO\TypesenseBundle\Manager\CollectionManager;
use AppBundle\Fixtures\DatabasePurger;
use Nucleos\UserBundle\Model\UserManager as UserManagerInterface;
use Nucleos\UserBundle\Util\UserManipulator;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Typesense\Exceptions\ObjectNotFound;

/**
 * The restaurant suggestions shown to admins (when inviting or editing a user,
 * in hubs, business restaurant groups…) come from /admin/restaurants/search.
 *
 * It used to return the raw Typesense documents, so restaurants that had been
 * (soft) deleted but were still in the index kept being suggested.
 */
class AdminRestaurantsSearchTest extends WebTestCase
{
    private KernelBrowser $client;

    protected function setUp(): void
    {
        parent::setUp();

        $this->client = static::createClient();

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

        // Contains "Wild Buffet", which is soft deleted,
        // but is indexed in Typesense when the fixtures are loaded
        $container->get('fidry_alice_data_fixtures.loader.doctrine')->load([
            __DIR__.'/../../../fixtures/ORM/sylius_channels.yml',
            __DIR__.'/../../../fixtures/ORM/sylius_locales.yml',
            __DIR__.'/../../../fixtures/ORM/products.yml',
            __DIR__.'/../../../fixtures/ORM/restaurants.yml',
        ]);

        $userManipulator = $container->get(UserManipulator::class);
        $userManipulator->create('admin', 'password123', 'admin@coopcycle.org', true, false);
        $userManipulator->addRole('admin', 'ROLE_ADMIN');

        $admin = $container->get(UserManagerInterface::class)->findUserByUsername('admin');

        $this->client->loginUser($admin, 'web');
    }

    public function testExcludesDeletedRestaurants()
    {
        $this->assertSame([], $this->search('Wild Buffet'));
    }

    public function testIncludesDisabledRestaurants()
    {
        $this->assertEqualsCanonicalizing([
            'Good Old Times',
            'Good Old Times with variables pricing',
        ], array_column($this->search('Good Old Times'), 'name'));
    }

    public function testReturnsIdAndName()
    {
        $results = $this->search('Nodaiwa');

        $this->assertCount(1, $results);
        $this->assertSame(['id', 'name'], array_keys($results[0]));
        $this->assertIsInt($results[0]['id']);
        $this->assertSame('Nodaiwa', $results[0]['name']);
    }

    private function search(string $q): array
    {
        $this->client->request('GET', '/admin/restaurants/search', ['q' => $q, 'format' => 'json']);

        $this->assertResponseIsSuccessful();

        return json_decode($this->client->getResponse()->getContent(), true);
    }
}
