<?php

namespace Tests\AppBundle\Api;

use ApiPlatform\Api\IriConverterInterface;
use ApiPlatform\Symfony\Bundle\Test\ApiTestCase;
use AppBundle\Entity\Incident\Incident;
use AppBundle\Entity\Sylius\Order;
use AppBundle\Fixtures\DatabasePurger;
use Doctrine\ORM\EntityManagerInterface;
use Lexik\Bundle\JWTAuthenticationBundle\Services\JWTTokenManagerInterface;
use Nucleos\UserBundle\Util\UserManipulator;
use Nucleos\UserBundle\Model\UserManager as UserManagerInterface;

/**
 * Covers the /api/incidents filters (customer/store/createdBy) and the
 * /api/incidents/filters endpoint's security, added in
 * "refact(incident): impl server-side filtering and pagination".
 */
class IncidentsFilteringFunctionalTest extends ApiTestCase
{
    private ?EntityManagerInterface $entityManager = null;
    private IriConverterInterface $iriConverter;

    protected function setUp(): void
    {
        parent::setUp();
        self::bootKernel();

        $this->entityManager = self::getContainer()->get(EntityManagerInterface::class);
        $this->iriConverter = self::getContainer()->get(IriConverterInterface::class);

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

    public function testCustomerFilterMatchesTheOrderCustomersUnderlyingUser(): void
    {
        $userManipulator = self::getContainer()->get(UserManipulator::class);
        $userManager = self::getContainer()->get(UserManagerInterface::class);
        $jwtManager = self::getContainer()->get(JWTTokenManagerInterface::class);
        $fixturesLoader = self::getContainer()->get('fidry_alice_data_fixtures.loader.doctrine');

        $fixturesLoader->load([
            __DIR__.'/../../../fixtures/ORM/sylius_taxation.yml',
            __DIR__.'/../../../fixtures/ORM/payment_methods.yml',
            __DIR__.'/../../../fixtures/ORM/sylius_products.yml',
            __DIR__.'/../../../fixtures/ORM/store_basic.yml',
            __DIR__.'/../../../fixtures/ORM/package_delivery_order.yml',
        ]);

        $userManipulator->create('dispatcher', 'password123', 'dispatcher@coopcycle.org', true, false);
        $userManipulator->addRole('dispatcher', 'ROLE_DISPATCHER');
        $dispatcher = $userManager->findUserByUsername('dispatcher');

        $userManipulator->create('jane', 'password123', 'jane@coopcycle.org', true, false);
        $customerUser = $userManager->findUserByUsername('jane');
        if (null === $customerUser->getCustomer()) {
            $customerUser->setCustomer(new \AppBundle\Entity\Sylius\Customer());
        }

        $userManipulator->create('john', 'password123', 'john@coopcycle.org', true, false);
        $otherUser = $userManager->findUserByUsername('john');
        if (null === $otherUser->getCustomer()) {
            $otherUser->setCustomer(new \AppBundle\Entity\Sylius\Customer());
        }

        /** @var Order $order */
        $order = $this->entityManager->getRepository(Order::class)->findOneBy(['number' => 'A1']);
        $order->setCustomer($customerUser->getCustomer());

        $incident = new Incident();
        $incident->setTask($order->getDelivery()->getDropoff());
        $incident->setTitle('Package was damaged');
        $incident->setDescription('Package was damaged');
        $incident->setCreatedBy($dispatcher);

        $this->entityManager->persist($customerUser);
        $this->entityManager->persist($otherUser);
        $this->entityManager->persist($order);
        $this->entityManager->persist($incident);
        $this->entityManager->flush();

        $token = $jwtManager->create($dispatcher);
        $client = static::createClient(defaultOptions: [
            'headers' => [
                'authorization' => 'Bearer '.$token,
            ],
        ]);

        $matchingCustomerIri = $this->iriConverter->getIriFromResource($customerUser);
        $response = $client->request('GET', '/api/incidents?customer='.urlencode($matchingCustomerIri));
        $this->assertResponseStatusCodeSame(200);
        $data = $response->toArray();
        $this->assertSame(1, $data['hydra:totalItems']);
        $this->assertSame($incident->getId(), $data['hydra:member'][0]['id']);

        $nonMatchingCustomerIri = $this->iriConverter->getIriFromResource($otherUser);
        $response = $client->request('GET', '/api/incidents?customer='.urlencode($nonMatchingCustomerIri));
        $this->assertResponseStatusCodeSame(200);
        $this->assertSame(0, $response->toArray()['hydra:totalItems']);
    }

    public function testFiltersEndpointRequiresDispatcherRole(): void
    {
        $userManipulator = self::getContainer()->get(UserManipulator::class);
        $userManager = self::getContainer()->get(UserManagerInterface::class);
        $jwtManager = self::getContainer()->get(JWTTokenManagerInterface::class);
        $fixturesLoader = self::getContainer()->get('fidry_alice_data_fixtures.loader.doctrine');

        $fixturesLoader->load([
            __DIR__.'/../../../fixtures/ORM/tasks.yml',
        ]);

        $userManipulator->create('bob', 'password123', 'bob@coopcycle.org', true, false);
        $userManipulator->addRole('bob', 'ROLE_COURIER');
        $courier = $userManager->findUserByUsername('bob');

        $userManipulator->create('dispatcher', 'password123', 'dispatcher@coopcycle.org', true, false);
        $userManipulator->addRole('dispatcher', 'ROLE_DISPATCHER');
        $dispatcher = $userManager->findUserByUsername('dispatcher');

        // A plain courier (no dispatcher role) must not access the filters endpoint,
        // even though it can access the main incidents collection.
        $courierToken = $jwtManager->create($courier);
        $client = static::createClient(defaultOptions: [
            'headers' => [
                'authorization' => 'Bearer '.$courierToken,
            ],
        ]);
        $client->request('GET', '/api/incidents/filters');
        $this->assertResponseStatusCodeSame(403);

        $dispatcherToken = $jwtManager->create($dispatcher);
        $client = static::createClient(defaultOptions: [
            'headers' => [
                'authorization' => 'Bearer '.$dispatcherToken,
            ],
        ]);
        $response = $client->request('GET', '/api/incidents/filters');
        $this->assertResponseStatusCodeSame(200);
        $data = $response->toArray();
        foreach (['stores', 'restaurants', 'authors', 'customers'] as $key) {
            $this->assertArrayHasKey($key, $data);
        }
    }
}
