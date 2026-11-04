<?php

namespace Tests\AppBundle\Controller;

use AppBundle\Entity\LocalBusiness;
use AppBundle\Entity\Store;
use AppBundle\Entity\Sylius\Order;
use AppBundle\Fixtures\DatabasePurger;
use Doctrine\ORM\EntityManagerInterface;
use Nucleos\UserBundle\Model\UserManager as UserManagerInterface;
use Nucleos\UserBundle\Util\UserManipulator;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\Component\DomCrawler\Crawler;

/**
 * Clicking the store/restaurant name on /admin/deliveries filters the list
 * with ?store=<id> or ?restaurant=<id>.
 *
 * It used to be ?filterField=s.id&filterValue=<id>, applied by KnpPaginator,
 * which crashed with a TypeError: its WhereWalker can't walk the
 * parenthesized subquery that firstPickupOnly() adds to the WHERE clause.
 */
class AdminDeliveriesFilterTest extends WebTestCase
{
    private KernelBrowser $client;
    private EntityManagerInterface $entityManager;
    private Store $store;

    protected function setUp(): void
    {
        parent::setUp();

        $this->client = static::createClient();

        $container = self::getContainer();

        $dbPurger = $container->get(DatabasePurger::class);
        $dbPurger->purge();
        $dbPurger->resetSequences();

        $container->get('fidry_alice_data_fixtures.loader.doctrine')->load([
            __DIR__.'/../../../fixtures/ORM/sylius_channels.yml',
            __DIR__.'/../../../fixtures/ORM/sylius_taxation.yml',
            __DIR__.'/../../../fixtures/ORM/payment_methods.yml',
            __DIR__.'/../../../fixtures/ORM/sylius_products.yml',
            __DIR__.'/../../../fixtures/ORM/store_basic.yml',
            __DIR__.'/../../../fixtures/ORM/package_delivery_order.yml',
            __DIR__.'/../../../fixtures/ORM/restaurant.yml',
        ]);

        $this->entityManager = $container->get(EntityManagerInterface::class);
        $this->store = $this->entityManager->getRepository(Store::class)->findOneBy(['name' => 'Acme']);

        $userManipulator = $container->get(UserManipulator::class);
        $userManipulator->create('admin', 'password123', 'admin@coopcycle.org', true, false);
        $userManipulator->addRole('admin', 'ROLE_ADMIN');

        $admin = $container->get(UserManagerInterface::class)->findUserByUsername('admin');

        $this->client->loginUser($admin, 'web');
    }

    public function testFiltersByStore()
    {
        $this->assertSame(1, $this->countDeliveries('store', $this->store->getId()));
        $this->assertSame(0, $this->countDeliveries('store', $this->store->getId() + 1));
    }

    public function testFiltersByRestaurant()
    {
        $restaurant = $this->entityManager->getRepository(LocalBusiness::class)->findOneBy(['name' => 'Bistro']);

        $order = $this->entityManager->getRepository(Order::class)->findOneBy(['number' => 'A1']);
        $order->addRestaurant($restaurant);
        $this->entityManager->flush();

        $this->assertSame(1, $this->countDeliveries('restaurant', $restaurant->getId()));
        $this->assertSame(0, $this->countDeliveries('restaurant', $restaurant->getId() + 1));
    }

    public function testFiltersThePastSection()
    {
        $this->entityManager->getConnection()->executeStatement(
            "UPDATE task SET done_after = done_after - interval '2 days', done_before = done_before - interval '2 days'"
        );

        $this->assertSame(1, $this->countDeliveries('store', $this->store->getId(), ['section' => 'past']));
        $this->assertSame(0, $this->countDeliveries('store', $this->store->getId() + 1, ['section' => 'past']));
        $this->assertSame(0, $this->countDeliveries('store', $this->store->getId(), ['section' => 'upcoming']));
    }

    public function testIgnoresTheFormerParameters()
    {
        $crawler = $this->request(['filterField' => 's.id', 'filterValue' => (string) ($this->store->getId() + 1)]);

        $this->assertCount(1, $crawler->filter('[data-testid="delivery__list_item"]'));
        $this->assertCount(0, $crawler->filter('[data-testid="deliveries__clear_filter"]'));
    }

    public function testIgnoresANonNumericOwner()
    {
        $crawler = $this->request(['store' => 'acme']);

        $this->assertCount(1, $crawler->filter('[data-testid="delivery__list_item"]'));
        $this->assertCount(0, $crawler->filter('[data-testid="deliveries__clear_filter"]'));
    }

    public function testLinksTheOwnerToItsFilter()
    {
        $crawler = $this->request(['page' => '1', 'section' => 'today']);

        $link = $crawler->filter('[data-testid="delivery__list_item"] a')->selectLink('Acme')->link();

        $this->assertSame(
            sprintf('/admin/deliveries?section=today&sort=d.createdAt&direction=desc&store=%d', $this->store->getId()),
            parse_url($link->getUri(), PHP_URL_PATH).'?'.parse_url($link->getUri(), PHP_URL_QUERY)
        );
    }

    public function testOffersToClearAnAppliedFilter()
    {
        $crawler = $this->request(['store' => (string) $this->store->getId(), 'section' => 'today']);

        $this->assertSame(
            '/admin/deliveries?section=today&sort=d.createdAt&direction=desc',
            $crawler->filter('[data-testid="deliveries__clear_filter"]')->attr('href')
        );
    }

    private function countDeliveries(string $owner, int $id, array $params = []): int
    {
        $crawler = $this->request(array_merge($params, [
            'sort' => 'd.createdAt',
            'direction' => 'desc',
            $owner => (string) $id,
        ]));

        return $crawler->filter('[data-testid="delivery__list_item"]')->count();
    }

    private function request(array $params): Crawler
    {
        $crawler = $this->client->request('GET', '/admin/deliveries', $params);

        $this->assertResponseIsSuccessful();

        return $crawler;
    }
}
