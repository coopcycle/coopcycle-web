<?php

namespace Tests\AppBundle\Controller;

use ACSEO\TypesenseBundle\Manager\CollectionManager;
use AppBundle\Entity\LocalBusiness;
use AppBundle\Fixtures\DatabasePurger;
use Doctrine\ORM\EntityManagerInterface;
use League\Csv\Reader as CsvReader;
use Nucleos\UserBundle\Model\UserManager as UserManagerInterface;
use Nucleos\UserBundle\Util\UserManipulator;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Typesense\Exceptions\ObjectNotFound;

/**
 * The products of a restaurant can be exported as a CSV file,
 * using the same columns as the file used to import products.
 */
class RestaurantProductsExportTest extends WebTestCase
{
    private KernelBrowser $client;
    private EntityManagerInterface $entityManager;
    private array $fixtures;

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

        // "Nodaiwa" has 2 products: "Pizza" and "Hamburger"
        $this->fixtures = $container->get('fidry_alice_data_fixtures.loader.doctrine')->load([
            __DIR__.'/../../../fixtures/ORM/sylius_channels.yml',
            __DIR__.'/../../../fixtures/ORM/sylius_locales.yml',
            __DIR__.'/../../../fixtures/ORM/products.yml',
            __DIR__.'/../../../fixtures/ORM/restaurants.yml',
        ]);

        $this->entityManager = $container->get(EntityManagerInterface::class);
    }

    private function loginAs(string $username, string $role, ?LocalBusiness $restaurant = null): void
    {
        $container = self::getContainer();

        $userManipulator = $container->get(UserManipulator::class);
        $userManipulator->create($username, 'password123', sprintf('%s@coopcycle.org', $username), true, false);
        $userManipulator->addRole($username, $role);

        $userManager = $container->get(UserManagerInterface::class);
        $user = $userManager->findUserByUsername($username);

        if (null !== $restaurant) {
            $user->addRestaurant($restaurant);
            $userManager->updateUser($user);
        }

        $this->client->loginUser($user, 'web');
    }

    private function export(string $url): array
    {
        $this->client->request('GET', $url, ['format' => 'csv']);

        $this->assertResponseIsSuccessful();
        $this->assertResponseHeaderSame('Content-Type', 'text/csv; charset=UTF-8');
        $this->assertResponseHeaderSame('Content-Disposition', 'attachment; filename=nodaiwa-products.csv');

        $reader = CsvReader::fromString($this->client->getInternalResponse()->getContent());
        $reader->setHeaderOffset(0);

        $this->assertSame(['name', 'description', 'price_tax_incl', 'tax_category'], $reader->getHeader());

        return iterator_to_array($reader->getRecords(), false);
    }

    public function testAdminCanExportProducts()
    {
        $this->loginAs('admin', 'ROLE_ADMIN');

        $restaurant = $this->fixtures['restaurant_1'];

        $records = $this->export(sprintf('/admin/restaurants/%d/products', $restaurant->getId()));

        $this->assertSame([
            [
                'name' => 'Pizza',
                'description' => '',
                'price_tax_incl' => '900',
                'tax_category' => 'TVA consommation immédiate',
            ],
            [
                'name' => 'Hamburger',
                'description' => '',
                'price_tax_incl' => '900',
                'tax_category' => 'TVA consommation immédiate',
            ],
        ], $records);
    }

    public function testExcludesDeletedProducts()
    {
        $this->loginAs('admin', 'ROLE_ADMIN');

        $restaurant = $this->fixtures['restaurant_1'];

        // Products are soft deleted
        $this->entityManager->remove($this->fixtures['hamburger']);
        $this->entityManager->flush();

        $this->assertNotNull($this->fixtures['hamburger']->getDeletedAt());

        $records = $this->export(sprintf('/admin/restaurants/%d/products', $restaurant->getId()));

        $this->assertSame(['Pizza'], array_column($records, 'name'));
    }

    public function testRestaurantOwnerCanExportProducts()
    {
        $restaurant = $this->fixtures['restaurant_1'];

        $this->loginAs('bob', 'ROLE_RESTAURANT', $restaurant);

        $records = $this->export(sprintf('/dashboard/restaurants/%d/products', $restaurant->getId()));

        $this->assertSame(['Pizza', 'Hamburger'], array_column($records, 'name'));
    }

    public function testRestaurantOwnerCannotExportProductsOfOtherRestaurants()
    {
        $this->loginAs('bob', 'ROLE_RESTAURANT', $this->fixtures['restaurant_2']);

        $this->client->request('GET', sprintf('/dashboard/restaurants/%d/products', $this->fixtures['restaurant_1']->getId()), [
            'format' => 'csv',
        ]);

        $this->assertResponseStatusCodeSame(403);
    }
}
