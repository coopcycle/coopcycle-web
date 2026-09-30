<?php

namespace Tests\AppBundle\SearchQuery;

use ApiPlatform\Metadata\IriConverterInterface;
use AppBundle\DataType\TsRange;
use AppBundle\Entity\LocalBusiness;
use AppBundle\Entity\LocalBusinessRepository;
use AppBundle\Entity\Sylius\Customer;
use AppBundle\Entity\Sylius\Order;
use AppBundle\Entity\Sylius\OrderRepository;
use AppBundle\Fixtures\DatabasePurger;
use AppBundle\SearchQuery\CustomerSearch;
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
        // Stored the way the app stores numbers everywhere: E.164.
        $customer->setPhoneNumber('+33612345678');
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

    public function testNumberAutocompleteReturnsContextAlongsideTheNumber(): void
    {
        // Order numbers alone are near-meaningless in the suggestion list -
        // each hit carries the delivery date, owner and customer so they can
        // be told apart (rendered as a muted second line by SearchQueryBar).
        $this->loadOrderFixture(); // store "Acme", customer jane.doe@example.com
        $this->authenticateAs('admin_search', 'ROLE_ADMIN');

        $response = $this->controller->number(
            Request::create('/search-query/orders/autocomplete:number', 'GET', ['q' => 'A1']),
            $this->orderRepository,
        );

        $hits = json_decode($response->getContent(), true)['hits'];
        $this->assertCount(1, $hits);
        $this->assertSame('Acme', $hits[0]['owner']);
        $this->assertSame('jane.doe@example.com', $hits[0]['customer']);
        $this->assertSame('2026-09-08', substr($hits[0]['date'], 0, 10));
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

    private function createCustomer(string $email, ?string $firstName, ?string $lastName, ?string $phoneNumber = null): void
    {
        $customer = new Customer();
        $customer->setEmail($email);
        $customer->setEmailCanonical($email);
        $customer->setFirstName($firstName);
        $customer->setLastName($lastName);
        $customer->setPhoneNumber($phoneNumber);

        $this->entityManager->persist($customer);
        $this->entityManager->flush();
    }

    /**
     * @return string[] the values (emails) of the customer hits for $q
     */
    private function customerHitValues(string $q): array
    {
        $response = $this->controller->customer(
            Request::create('/search-query/orders/autocomplete:customer', 'GET', ['q' => $q]),
            $this->entityManager,
        );

        return array_column(json_decode($response->getContent(), true)['hits'], 'value');
    }

    public function testCustomerAutocompleteMatchesOnFullName(): void
    {
        $this->loadOrderFixture(); // Jane Doe <jane.doe@example.com>
        $this->authenticateAs('admin_search', 'ROLE_ADMIN');

        // Either half of the name on its own, or both - the reason this
        // scores with WORD_SIMILARITY and not SIMILARITY, which would give
        // "doe" against "Jane Doe" only 0.28 and miss it.
        $this->assertSame(['jane.doe@example.com'], $this->customerHitValues('Jane'));
        $this->assertSame(['jane.doe@example.com'], $this->customerHitValues('doe'));
        $this->assertSame(['jane.doe@example.com'], $this->customerHitValues('jane doe'));
    }

    public function testCustomerAutocompleteToleratesTyposInTheName(): void
    {
        $this->loadOrderFixture();
        $this->authenticateAs('admin_search', 'ROLE_ADMIN');

        $this->assertSame(['jane.doe@example.com'], $this->customerHitValues('jann'));
    }

    public function testCustomerAutocompleteMatchesACustomerWithOnlyOneHalfOfAName(): void
    {
        // CONCAT over a NULL gives NULL in Postgres, so without the COALESCE
        // in CustomerSearch::fullNameExpr() this customer would never match.
        $this->loadOrderFixture();
        $this->createCustomer('mononym@example.com', 'Prince', null);
        $this->authenticateAs('admin_search', 'ROLE_ADMIN');

        $this->assertSame(['mononym@example.com'], $this->customerHitValues('Prince'));
    }

    public function testCustomerAutocompleteMatchesAPhoneNumberTypedInLocalFormat(): void
    {
        // Stored as "+33612345678"; an admin reads it off an order in any of
        // these forms. None of them is the stored string.
        $this->loadOrderFixture();
        $this->authenticateAs('admin_search', 'ROLE_ADMIN');

        $this->assertSame(['jane.doe@example.com'], $this->customerHitValues('0612345678'));
        $this->assertSame(['jane.doe@example.com'], $this->customerHitValues('06 12 34 56 78'));
        $this->assertSame(['jane.doe@example.com'], $this->customerHitValues('+33 6 12 34 56 78'));
        $this->assertSame(['jane.doe@example.com'], $this->customerHitValues('+33612345678'));
        // Half-typed, as the suggestion list is being built keystroke by
        // keystroke.
        $this->assertSame(['jane.doe@example.com'], $this->customerHitValues('061234'));
    }

    public function testCustomerAutocompleteDoesNotFuzzyMatchPhoneNumbers(): void
    {
        // One digit off is a different subscriber, not a typo to forgive.
        $this->loadOrderFixture();
        $this->authenticateAs('admin_search', 'ROLE_ADMIN');

        $this->assertSame([], $this->customerHitValues('0612345679'));
    }

    public function testCustomerAutocompleteIgnoresTooShortPhoneNumbers(): void
    {
        // "06" is in every French mobile number - matching on it would just
        // return the table.
        $this->loadOrderFixture();
        $this->authenticateAs('admin_search', 'ROLE_ADMIN');

        $this->assertSame([], $this->customerHitValues('06'));
    }

    public function testCustomerAutocompleteRanksAPhoneNumberMatchFirst(): void
    {
        // An exact phone match scores 1, above anything fuzzy.
        $this->loadOrderFixture(); // Jane Doe, +33612345678
        $this->createCustomer('0612345678@example.com', 'Look', 'Alike');
        $this->authenticateAs('admin_search', 'ROLE_ADMIN');

        $values = $this->customerHitValues('0612345678');
        $this->assertSame('jane.doe@example.com', $values[0]);
    }

    public function testCustomerAutocompleteReportsThePhoneNumberOfAHit(): void
    {
        $this->loadOrderFixture();
        $this->authenticateAs('admin_search', 'ROLE_ADMIN');

        $response = $this->controller->customer(
            Request::create('/search-query/orders/autocomplete:customer', 'GET', ['q' => '0612345678']),
            $this->entityManager,
        );

        $hits = json_decode($response->getContent(), true)['hits'];
        $this->assertSame('+33612345678', $hits[0]['telephone']);
    }

    /**
     * The two expression indexes from Version20260930090000 only get used if
     * Postgres sees the identical expression in the query - and a mismatch
     * changes nothing about the results, only about how long they take, so
     * no other test here would notice. Hence asserting the plan.
     *
     * enable_seqscan is disabled because the test database holds a handful
     * of customers, where a sequential scan is genuinely the cheaper plan;
     * turning it off makes Postgres show whether it *can* use the indexes,
     * which is the question. Parameters are all text, so substituting one
     * literal for every placeholder gives a representative query.
     */
    public function testCustomerAutocompleteQueryCanUseTheTrigramIndexes(): void
    {
        $this->loadOrderFixture();

        $connection = $this->entityManager->getConnection();

        // The test database is built from the schema, so the migration's
        // indexes aren't there - create them from the same definitions it
        // uses. Not CONCURRENTLY: that is for not locking a live table.
        foreach (CustomerSearch::indexDefinitions() as $name => $definition) {
            $connection->executeStatement(sprintf(
                'CREATE INDEX IF NOT EXISTS %s ON %s', $name, $definition
            ));
        }

        $builder = new \ReflectionMethod($this->controller, 'customerQueryBuilder');
        $builder->setAccessible(true);
        $qb = $builder->invoke($this->controller, $this->entityManager, '0612345678');

        // Every parameter in this query is text, so one literal stands in
        // for all of them; the wildcards matter, as it's a LIKE that has to
        // reach the phone index.
        $sql = str_replace('?', "'%612345678%'", $qb->getQuery()->getSQL());

        // The fixture holds a handful of customers, where a sequential scan
        // genuinely is the cheaper plan - disabling it asks Postgres whether
        // it *can* use the indexes, which is the actual question.
        $connection->executeStatement('SET enable_seqscan = off');

        try {
            $plan = implode("\n", $connection->fetchFirstColumn('EXPLAIN ' . $sql));
        } finally {
            $connection->executeStatement('RESET enable_seqscan');
        }

        foreach (array_keys(CustomerSearch::indexDefinitions()) as $name) {
            $this->assertStringContainsString($name, $plan, "Query cannot use $name:\n$plan");
        }
    }

    public function testCustomerAutocompleteReturnsEmptyHitsForEmptyQuery(): void
    {
        $this->loadOrderFixture();
        $this->authenticateAs('admin_search', 'ROLE_ADMIN');

        $this->assertSame([], $this->customerHitValues(''));
    }

    public function testCustomerAutocompleteRequiresAdminRole(): void
    {
        $this->authenticateAs('regular_user_customer', 'ROLE_USER');

        $this->expectException(AccessDeniedException::class);

        $this->customerHitValues('jane');
    }

    /**
     * @return string[] the labels of the owner hits for $q
     */
    private function ownerHitLabels(string $q): array
    {
        $response = $this->controller->owner(
            Request::create('/search-query/orders/autocomplete:owner', 'GET', ['q' => $q]),
            $this->entityManager,
            self::getContainer()->get(LocalBusinessRepository::class),
            self::getContainer()->get(IriConverterInterface::class),
        );

        return array_column(json_decode($response->getContent(), true)['hits'], 'label');
    }

    private function createRestaurant(string $name): void
    {
        $restaurant = new LocalBusiness();
        $restaurant->setName($name);
        $this->entityManager->persist($restaurant);
        $this->entityManager->flush();
    }

    public function testOwnerAutocompleteIgnoresCase(): void
    {
        // The reported bug: a LIKE '%...%' meant "fidu" never found
        // "Fiducial". SIMILARITY is case-insensitive, pg_trgm lowercasing
        // trigrams before comparing.
        $this->createRestaurant('Fiducial');
        $this->authenticateAs('admin_search', 'ROLE_ADMIN');

        $this->assertSame(['Fiducial'], $this->ownerHitLabels('fidu'));
        $this->assertSame(['Fiducial'], $this->ownerHitLabels('FIDU'));
        $this->assertSame(['Fiducial'], $this->ownerHitLabels('Fiducial'));
    }

    public function testOwnerAutocompleteToleratesTypos(): void
    {
        $this->createRestaurant('Fiducial');
        $this->createRestaurant('La casserole noire gitane');
        $this->authenticateAs('admin_search', 'ROLE_ADMIN');

        $this->assertSame(['Fiducial'], $this->ownerHitLabels('fiducal'));
        $this->assertSame(['La casserole noire gitane'], $this->ownerHitLabels('caserole'));
    }

    public function testOwnerAutocompleteMatchesOneWordOfAMultiWordName(): void
    {
        // Why this uses WORD_SIMILARITY and not the SIMILARITY that
        // number()/customer() use: against the whole string, a single word of
        // a long name scores ~0.1 and would be missed entirely.
        $this->createRestaurant('Fruits & légumes à domicile');
        $this->authenticateAs('admin_search', 'ROLE_ADMIN');

        $this->assertSame(['Fruits & légumes à domicile'], $this->ownerHitLabels('legume'));
        $this->assertSame(['Fruits & légumes à domicile'], $this->ownerHitLabels('domicile'));
    }

    public function testOwnerAutocompleteDoesNotMatchUnrelatedNames(): void
    {
        // The flip side of being forgiving - the threshold still has to keep
        // genuinely different names out.
        $this->createRestaurant('La casserole noire gitane');
        $this->createRestaurant('Colis prompto');
        $this->authenticateAs('admin_search', 'ROLE_ADMIN');

        $this->assertSame(['Colis prompto'], $this->ownerHitLabels('colis'));
        $this->assertSame([], $this->ownerHitLabels('resto'));
    }

    public function testOwnerAutocompleteMatchesStoresAsWellAsRestaurants(): void
    {
        $this->loadOrderFixture(); // store "Acme"
        $this->createRestaurant('Bistro');
        $this->authenticateAs('admin_search', 'ROLE_ADMIN');

        $this->assertSame(['Acme'], $this->ownerHitLabels('acme'));
        $this->assertSame(['Bistro'], $this->ownerHitLabels('bistr'));
    }

    public function testOwnerAutocompleteReturnsNoMatchesForUnrelatedQuery(): void
    {
        $this->createRestaurant('Fiducial');
        $this->authenticateAs('admin_search', 'ROLE_ADMIN');

        $this->assertSame([], $this->ownerHitLabels('totally-unrelated-string'));
    }

    public function testOwnerAutocompleteReturnsEmptyHitsForEmptyQuery(): void
    {
        $this->createRestaurant('Fiducial');
        $this->authenticateAs('admin_search', 'ROLE_ADMIN');

        $this->assertSame([], $this->ownerHitLabels(''));
    }

    public function testOwnerAutocompleteRequiresAdminRole(): void
    {
        $this->authenticateAs('regular_user_owner', 'ROLE_USER');

        $this->expectException(AccessDeniedException::class);

        $this->ownerHitLabels('fidu');
    }
}
