<?php

namespace Tests\AppBundle\SearchQuery;

use AppBundle\Entity\Sylius\Customer;
use AppBundle\Entity\Sylius\Order;
use AppBundle\Entity\Sylius\OrderRepository;
use AppBundle\Fixtures\DatabasePurger;
use AppBundle\SearchQuery\OrdersAutocompleteController;
use Doctrine\ORM\EntityManagerInterface;
use Nucleos\UserBundle\Model\UserManager as UserManagerInterface;
use Nucleos\UserBundle\Util\UserManipulator;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Security\Core\Authentication\Token\UsernamePasswordToken;
use Symfony\Component\Security\Core\Authentication\Token\Storage\TokenStorageInterface;
use Symfony\Component\Security\Core\Exception\AccessDeniedException;

/**
 * Covers AppBundle\SearchQuery\OrdersAutocompleteController::number() and
 * ::customer() (the /search-query/orders/autocomplete:number and
 * /search-query/orders/autocomplete:customer endpoints).
 *
 * Calls the controller actions directly rather than through a full HTTP
 * request, since admin authentication in this app goes through a 2FA
 * challenge that a plain simulated session can't satisfy - the ROLE_ADMIN
 * gate itself is a single `isGranted()` call identical to (and no riskier
 * than) the pre-existing, likewise-untested owner() action, so the value is
 * in exercising the SIMILARITY()-based fuzzy matching, not re-proving the
 * framework's own security voter.
 */
class OrdersAutocompleteControllerTest extends KernelTestCase
{
    private ?EntityManagerInterface $entityManager = null;
    private OrdersAutocompleteController $controller;
    private OrderRepository $orderRepository;
    private TokenStorageInterface $tokenStorage;

    protected function setUp(): void
    {
        parent::setUp();
        self::bootKernel();

        $this->entityManager = self::getContainer()->get(EntityManagerInterface::class);
        $this->controller = self::getContainer()->get(OrdersAutocompleteController::class);
        $this->orderRepository = self::getContainer()->get(OrderRepository::class);
        $this->tokenStorage = self::getContainer()->get(TokenStorageInterface::class);

        $dbPurger = self::getContainer()->get(DatabasePurger::class);
        $dbPurger->purge();
        $dbPurger->resetSequences();
    }

    protected function tearDown(): void
    {
        parent::tearDown();

        $this->tokenStorage->setToken(null);
        $this->entityManager->close();
        $this->entityManager = null;
    }

    private function authenticateAs(string $username, string $role): void
    {
        $userManipulator = self::getContainer()->get(UserManipulator::class);
        $userManager = self::getContainer()->get(UserManagerInterface::class);

        $userManipulator->create($username, 'password123', $username . '@coopcycle.org', true, false);
        $userManipulator->addRole($username, $role);

        $user = $userManager->findUserByUsername($username);
        $this->tokenStorage->setToken(new UsernamePasswordToken($user, 'main', [$role]));
    }

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

        $this->entityManager->persist($customer);
        $this->entityManager->persist($order);
        $this->entityManager->flush();

        return $order;
    }

    public function testNumberAutocompleteReturnsFuzzyMatches(): void
    {
        $this->loadOrderFixture();
        $this->authenticateAs('admin_search', 'ROLE_ADMIN');

        $response = $this->controller->number(
            Request::create('/search-query/orders/autocomplete:number', 'GET', ['q' => 'A1']),
            $this->orderRepository,
        );

        $hits = json_decode($response->getContent(), true)['hits'];
        $this->assertCount(1, $hits);
        $this->assertSame('A1', $hits[0]['label']);
        $this->assertSame('A1', $hits[0]['value']);
    }

    public function testNumberAutocompleteReturnsNoMatchesForUnrelatedQuery(): void
    {
        $this->loadOrderFixture();
        $this->authenticateAs('admin_search', 'ROLE_ADMIN');

        $response = $this->controller->number(
            Request::create('/search-query/orders/autocomplete:number', 'GET', ['q' => 'zzzzzzzzzz']),
            $this->orderRepository,
        );

        $this->assertSame([], json_decode($response->getContent(), true)['hits']);
    }

    public function testNumberAutocompleteReturnsEmptyHitsForEmptyQuery(): void
    {
        $this->loadOrderFixture();
        $this->authenticateAs('admin_search', 'ROLE_ADMIN');

        $response = $this->controller->number(
            Request::create('/search-query/orders/autocomplete:number', 'GET', ['q' => '']),
            $this->orderRepository,
        );

        $this->assertSame([], json_decode($response->getContent(), true)['hits']);
    }

    public function testNumberAutocompleteRequiresAdminRole(): void
    {
        $this->authenticateAs('courier_search', 'ROLE_COURIER');

        $this->expectException(AccessDeniedException::class);

        $this->controller->number(
            Request::create('/search-query/orders/autocomplete:number', 'GET', ['q' => 'A1']),
            $this->orderRepository,
        );
    }

    public function testCustomerAutocompleteReturnsFuzzyMatchOnEmail(): void
    {
        $this->loadOrderFixture();
        $this->authenticateAs('admin_search', 'ROLE_ADMIN');

        $response = $this->controller->customer(
            Request::create('/search-query/orders/autocomplete:customer', 'GET', ['q' => 'jane.doe@example.com']),
            $this->entityManager,
        );

        $hits = json_decode($response->getContent(), true)['hits'];
        $this->assertCount(1, $hits);
        $this->assertSame('jane.doe@example.com', $hits[0]['value']);
        $this->assertSame('Jane Doe (jane.doe@example.com)', $hits[0]['label']);
    }

    public function testCustomerAutocompleteToleratesTypos(): void
    {
        $this->loadOrderFixture();
        $this->authenticateAs('admin_search', 'ROLE_ADMIN');

        // one character off ("jane.doa" instead of "jane.doe") - this is
        // exactly what SIMILARITY() (trigram fuzzy matching) is for.
        $response = $this->controller->customer(
            Request::create('/search-query/orders/autocomplete:customer', 'GET', ['q' => 'jane.doa@example.com']),
            $this->entityManager,
        );

        $hits = json_decode($response->getContent(), true)['hits'];
        $this->assertCount(1, $hits);
        $this->assertSame('jane.doe@example.com', $hits[0]['value']);
    }

    public function testCustomerAutocompleteReturnsNoMatchesForUnrelatedQuery(): void
    {
        $this->loadOrderFixture();
        $this->authenticateAs('admin_search', 'ROLE_ADMIN');

        $response = $this->controller->customer(
            Request::create('/search-query/orders/autocomplete:customer', 'GET', ['q' => 'totally-unrelated-string']),
            $this->entityManager,
        );

        $this->assertSame([], json_decode($response->getContent(), true)['hits']);
    }
}
