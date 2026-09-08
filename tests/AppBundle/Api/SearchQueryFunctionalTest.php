<?php

namespace Tests\AppBundle\Api;

use ApiPlatform\Symfony\Bundle\Test\ApiTestCase;
use AppBundle\Entity\SearchQuery;
use AppBundle\Fixtures\DatabasePurger;
use Doctrine\ORM\EntityManagerInterface;
use Lexik\Bundle\JWTAuthenticationBundle\Services\JWTTokenManagerInterface;
use Nucleos\UserBundle\Model\UserManager as UserManagerInterface;
use Nucleos\UserBundle\Util\UserManipulator;

/**
 * Covers /api/search_queries (create/get/delete) and /api/me/search_queries
 * (list mine, optionally filtered by scope) - the "save a search bar query"
 * feature backing js/app/components/SearchQueryBar.
 *
 * Uses response->getStatusCode()/toArray() directly rather than the
 * BrowserKitAssertionsTrait helpers (assertResponseStatusCodeSame etc.) -
 * those implicitly assert against whichever client static::createClient()
 * registered last, which breaks as soon as a test juggles more than one
 * authenticated client (needed here to prove per-user scoping).
 */
class SearchQueryFunctionalTest extends ApiTestCase
{
    private ?EntityManagerInterface $entityManager = null;

    protected function setUp(): void
    {
        parent::setUp();
        self::bootKernel();

        $this->entityManager = self::getContainer()->get(EntityManagerInterface::class);

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

    private function authenticatedClient(string $username)
    {
        $userManipulator = self::getContainer()->get(UserManipulator::class);
        $userManager = self::getContainer()->get(UserManagerInterface::class);
        $jwtManager = self::getContainer()->get(JWTTokenManagerInterface::class);

        $userManipulator->create($username, 'password123', $username . '@coopcycle.org', true, false);
        $user = $userManager->findUserByUsername($username);

        $token = $jwtManager->create($user);

        return static::createClient(defaultOptions: [
            'headers' => ['authorization' => 'Bearer ' . $token],
        ]);
    }

    public function testCreateAndListMine(): void
    {
        $client = $this->authenticatedClient('alice');

        $response = $client->request('POST', '/api/search_queries', [
            'json' => [
                'scope' => 'orders',
                'query' => '-state:cancelled owner:Acme',
                'name' => 'My orders',
            ],
        ]);
        $this->assertSame(201, $response->getStatusCode());
        $created = $response->toArray();
        $this->assertSame('orders', $created['scope']);
        $this->assertSame('-state:cancelled owner:Acme', $created['query']);
        $this->assertSame('My orders', $created['name']);
        $this->assertArrayHasKey('createdAt', $created);

        $response = $client->request('GET', '/api/me/search_queries?scope=orders');
        $this->assertSame(200, $response->getStatusCode());
        $items = $response->toArray()['hydra:member'];
        $this->assertCount(1, $items);
        $this->assertSame('My orders', $items[0]['name']);
    }

    public function testMineOnlyReturnsOwnQueries(): void
    {
        $aliceClient = $this->authenticatedClient('alice');
        $bobClient = $this->authenticatedClient('bob');

        $response = $aliceClient->request('POST', '/api/search_queries', [
            'json' => ['scope' => 'orders', 'query' => 'owner:Acme', 'name' => "Alice's search"],
        ]);
        $this->assertSame(201, $response->getStatusCode());

        $bobResponse = $bobClient->request('GET', '/api/me/search_queries?scope=orders');
        $this->assertSame([], $bobResponse->toArray()['hydra:member']);

        $aliceResponse = $aliceClient->request('GET', '/api/me/search_queries?scope=orders');
        $this->assertCount(1, $aliceResponse->toArray()['hydra:member']);
    }

    public function testMineFiltersByScope(): void
    {
        $client = $this->authenticatedClient('alice');

        $response = $client->request('POST', '/api/search_queries', [
            'json' => ['scope' => 'orders', 'query' => 'owner:Acme', 'name' => 'Orders search'],
        ]);
        $this->assertSame(201, $response->getStatusCode());

        $response = $client->request('POST', '/api/search_queries', [
            'json' => ['scope' => 'deliveries', 'query' => 'owner:Acme', 'name' => 'Deliveries search'],
        ]);
        $this->assertSame(201, $response->getStatusCode());

        $items = $client->request('GET', '/api/me/search_queries?scope=orders')->toArray()['hydra:member'];
        $this->assertCount(1, $items);
        $this->assertSame('Orders search', $items[0]['name']);

        $items = $client->request('GET', '/api/me/search_queries?scope=deliveries')->toArray()['hydra:member'];
        $this->assertCount(1, $items);
        $this->assertSame('Deliveries search', $items[0]['name']);
    }

    public function testCreateRequiresAuthentication(): void
    {
        $client = static::createClient();

        $response = $client->request('POST', '/api/search_queries', [
            'json' => ['scope' => 'orders', 'query' => 'owner:Acme', 'name' => 'Anonymous'],
        ]);
        $this->assertSame(401, $response->getStatusCode());
    }

    public function testCreateRejectsBlankFields(): void
    {
        $client = $this->authenticatedClient('alice');

        $response = $client->request('POST', '/api/search_queries', [
            'json' => ['scope' => '', 'query' => 'owner:Acme', 'name' => ''],
        ]);
        // API Platform maps validation failures to 400 in this app - see
        // config/packages/api_platform.yaml's exception_to_status.
        $this->assertSame(400, $response->getStatusCode());
    }

    public function testOwnerCanDeleteTheirOwnQuery(): void
    {
        $client = $this->authenticatedClient('alice');

        $response = $client->request('POST', '/api/search_queries', [
            'json' => ['scope' => 'orders', 'query' => 'owner:Acme', 'name' => 'To delete'],
        ]);
        $id = $response->toArray()['id'];

        $response = $client->request('DELETE', "/api/search_queries/{$id}");
        $this->assertSame(204, $response->getStatusCode());

        $this->assertNull($this->entityManager->getRepository(SearchQuery::class)->find($id));
    }

    public function testCannotDeleteAnotherUsersQuery(): void
    {
        $aliceClient = $this->authenticatedClient('alice');
        $bobClient = $this->authenticatedClient('bob');

        $response = $aliceClient->request('POST', '/api/search_queries', [
            'json' => ['scope' => 'orders', 'query' => 'owner:Acme', 'name' => "Alice's search"],
        ]);
        $id = $response->toArray()['id'];

        $response = $bobClient->request('DELETE', "/api/search_queries/{$id}");
        $this->assertSame(403, $response->getStatusCode());
    }
}
