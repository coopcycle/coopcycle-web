<?php

namespace Tests\AppBundle\SearchQuery;

use AppBundle\Entity\LocalBusiness;
use AppBundle\Entity\Sylius\Customer;
use AppBundle\Entity\Sylius\Order;
use AppBundle\Entity\Sylius\OrderRepository;
use AppBundle\Entity\Sylius\OrderVendor;
use AppBundle\Fixtures\DatabasePurger;
use AppBundle\SearchQuery\Orders;
use AppBundle\DataType\TsRange;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;

/**
 * Covers AppBundle\SearchQuery\Orders - the "key:value" query-building logic
 * that used to live inline in AdminController::getOrderList.
 */
class OrdersTest extends KernelTestCase
{
    private ?EntityManagerInterface $entityManager = null;
    private Orders $orders;
    private OrderRepository $orderRepository;

    protected function setUp(): void
    {
        parent::setUp();
        self::bootKernel();

        $this->entityManager = self::getContainer()->get(EntityManagerInterface::class);
        $this->orders = self::getContainer()->get(Orders::class);
        $this->orderRepository = self::getContainer()->get(OrderRepository::class);

        $dbPurger = self::getContainer()->get(DatabasePurger::class);
        $dbPurger->purge();
        $dbPurger->resetSequences();
    }

    protected function tearDown(): void
    {
        parent::tearDown();

        $this->entityManager->close();
        $this->entityManager = null;
    }

    /**
     * Loads a single package-delivery order (number "A1", state "new"),
     * owned by the store "Acme", and gives it a customer + shipping time
     * range so every filter has something concrete to match against.
     */
    private function loadOrderFixture(): Order
    {
        $fixturesLoader = self::getContainer()->get('fidry_alice_data_fixtures.loader.doctrine');

        $fixturesLoader->load([
            __DIR__.'/../../../fixtures/ORM/sylius_taxation.yml',
            __DIR__.'/../../../fixtures/ORM/payment_methods.yml',
            __DIR__.'/../../../fixtures/ORM/sylius_products.yml',
            __DIR__.'/../../../fixtures/ORM/store_basic.yml',
            __DIR__.'/../../../fixtures/ORM/package_delivery_order.yml',
        ]);

        /** @var Order $order */
        $order = $this->entityManager->getRepository(Order::class)->findOneBy(['number' => 'A1']);

        $customer = new Customer();
        $customer->setFirstName('Jane');
        $customer->setLastName('Doe');
        $customer->setEmail('jane.doe@example.com');
        $customer->setEmailCanonical('jane.doe@example.com');
        $order->setCustomer($customer);

        $order->setShippingTimeRange(TsRange::create(
            new \DateTime('2026-09-08 12:00:00'),
            new \DateTime('2026-09-08 12:30:00')
        ));

        $this->entityManager->persist($customer);
        $this->entityManager->persist($order);
        $this->entityManager->flush();

        return $order;
    }

    /**
     * @return Order[]
     */
    private function search(string $query): array
    {
        $qb = $this->orders->search($query, $this->orderRepository->createOptimizedQueryBuilder('o'));

        return $qb->getQuery()->getResult();
    }

    public function testEmptyQueryDefaultsToExcludingCartOrders(): void
    {
        $this->loadOrderFixture();

        $results = $this->search('');

        $this->assertCount(1, $results);
    }

    public function testNumberFilterMatchesPartialCaseInsensitive(): void
    {
        $order = $this->loadOrderFixture();

        $results = $this->search('number:a1');

        $this->assertCount(1, $results);
        $this->assertSame($order->getId(), $results[0]->getId());
    }

    public function testNumberFilterExcludesNonMatching(): void
    {
        $this->loadOrderFixture();

        $results = $this->search('number:does-not-exist');

        $this->assertCount(0, $results);
    }

    public function testExcludedNumberFilter(): void
    {
        $this->loadOrderFixture();

        $this->assertCount(0, $this->search('-number:a1'));
        $this->assertCount(1, $this->search('-number:does-not-exist'));
    }

    public function testCustomerFilterMatchesEmailOrName(): void
    {
        $order = $this->loadOrderFixture();

        foreach (['jane.doe@example.com', 'jane', 'doe'] as $needle) {
            $results = $this->search('customer:' . $needle);
            $this->assertCount(1, $results, "Expected a match for customer:$needle");
            $this->assertSame($order->getId(), $results[0]->getId());
        }

        $this->assertCount(0, $this->search('customer:nobody'));
    }

    public function testExcludedCustomerFilter(): void
    {
        $this->loadOrderFixture();

        $this->assertCount(0, $this->search('-customer:jane'));
        $this->assertCount(1, $this->search('-customer:nobody'));
    }

    public function testDateFilterMatchesOverlappingShippingTimeRange(): void
    {
        $order = $this->loadOrderFixture();

        $results = $this->search('date:2026-09-08');

        $this->assertCount(1, $results);
        $this->assertSame($order->getId(), $results[0]->getId());

        $this->assertCount(0, $this->search('date:2026-09-09'));
    }

    public function testExcludedDateFilter(): void
    {
        $this->loadOrderFixture();

        $this->assertCount(0, $this->search('-date:2026-09-08'));
        $this->assertCount(1, $this->search('-date:2026-09-09'));
    }

    public function testInvalidDateFilterIsIgnored(): void
    {
        $this->loadOrderFixture();

        // e.g. while the user is still typing "date:2026-09-0" - should not throw
        $results = $this->search('date:not-a-date');

        $this->assertCount(1, $results);
    }

    public function testStateFilterIncludesOnlyMatchingStates(): void
    {
        $this->loadOrderFixture(); // state "new"

        $this->assertCount(1, $this->search('state:new'));
        $this->assertCount(0, $this->search('state:accepted'));
        $this->assertCount(1, $this->search('state:new state:accepted'));
    }

    public function testStateFilterExcludesMatchingStates(): void
    {
        $this->loadOrderFixture(); // state "new"

        $this->assertCount(0, $this->search('-state:new'));
        $this->assertCount(1, $this->search('-state:accepted'));
    }

    public function testOwnerFilterMatchesStoreByName(): void
    {
        $order = $this->loadOrderFixture(); // owned by store "Acme"

        $results = $this->search('owner:Acme');

        $this->assertCount(1, $results);
        $this->assertSame($order->getId(), $results[0]->getId());

        $this->assertCount(0, $this->search('owner:DoesNotExist'));
    }

    public function testExcludedOwnerFilterOnKnownStore(): void
    {
        $this->loadOrderFixture(); // owned by store "Acme"

        $this->assertCount(0, $this->search('-owner:Acme'));
    }

    public function testExcludedOwnerFilterOnUnknownOwnerIsANoOp(): void
    {
        // An exclusive filter on an owner that doesn't exist excludes
        // nothing, rather than being silently ignored or erroring.
        $this->loadOrderFixture();

        $this->assertCount(1, $this->search('-owner:DoesNotExist'));
    }

    public function testOwnerFilterMatchesRestaurantByName(): void
    {
        $order = $this->loadOrderFixture();

        $restaurant = new LocalBusiness();
        $restaurant->setName('Bistro');
        $this->entityManager->persist($restaurant);
        $this->entityManager->persist(new OrderVendor($order, $restaurant));
        $this->entityManager->flush();

        $results = $this->search('owner:Bistro');

        $this->assertCount(1, $results);
        $this->assertSame($order->getId(), $results[0]->getId());
    }

    public function testExcludedOwnerFilterMatchesRestaurantByName(): void
    {
        $order = $this->loadOrderFixture();

        $restaurant = new LocalBusiness();
        $restaurant->setName('Bistro');
        $this->entityManager->persist($restaurant);
        $this->entityManager->persist(new OrderVendor($order, $restaurant));
        $this->entityManager->flush();

        $this->assertCount(0, $this->search('-owner:Bistro'));
        $this->assertCount(1, $this->search('-owner:DoesNotExist'));
    }

    public function testCombinedFilters(): void
    {
        $order = $this->loadOrderFixture();

        $results = $this->search('number:a1 customer:jane date:2026-09-08 state:new owner:Acme');

        $this->assertCount(1, $results);
        $this->assertSame($order->getId(), $results[0]->getId());

        $this->assertCount(0, $this->search('number:a1 customer:nobody'));
    }
}
