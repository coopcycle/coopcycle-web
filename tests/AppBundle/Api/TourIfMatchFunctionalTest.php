<?php

namespace Tests\AppBundle\Api;

use ApiPlatform\Symfony\Bundle\Test\ApiTestCase;
use AppBundle\Entity\Address;
use AppBundle\Entity\Base\GeoCoordinates;
use AppBundle\Entity\Task;
use AppBundle\Fixtures\DatabasePurger;
use Doctrine\ORM\EntityManagerInterface;
use Lexik\Bundle\JWTAuthenticationBundle\Services\JWTTokenManagerInterface;
use Nucleos\UserBundle\Util\UserManipulator;
use Nucleos\UserBundle\Model\UserManager as UserManagerInterface;

/**
 * A tour is replaced wholesale, so a write built from a stale read silently
 * drops whatever the client had not heard about yet.
 *
 * @see https://github.com/coopcycle/coopcycle-web/issues/5372
 */
class TourIfMatchFunctionalTest extends ApiTestCase
{
    private $entityManager;

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

    private function createTask(\DateTime $date, int $minute): Task
    {
        $address = new Address();
        $address->setStreetAddress(sprintf('%d, rue de Test, Paris', $minute));
        $address->setAddressLocality('Paris');
        $address->setPostalCode('75001');
        $address->setGeo(new GeoCoordinates('48.86'.str_pad((string) $minute, 4, '0', STR_PAD_LEFT), '2.33'));

        $task = new Task();
        $task->setAddress($address);
        $task->setAfter((clone $date)->setTime(10, 0));
        $task->setBefore((clone $date)->setTime(18, 0));

        $this->entityManager->persist($address);
        $this->entityManager->persist($task);

        return $task;
    }

    private function bootstrapAdmin()
    {
        $userManipulator = self::getContainer()->get(UserManipulator::class);
        $userManager = self::getContainer()->get(UserManagerInterface::class);
        $fixturesLoader = self::getContainer()->get('fidry_alice_data_fixtures.loader.doctrine');

        $fixturesLoader->load([
            __DIR__.'/../../../fixtures/ORM/settings_mandatory.yml',
            __DIR__.'/../../../fixtures/ORM/sylius_channels.yml',
        ], $_SERVER);

        $userManipulator->create('dispatcher', '12345678', 'dispatcher@demo.coopcycle.org', true, false);
        $userManipulator->addRole('dispatcher', 'ROLE_ADMIN');

        return $userManager->findUserByUsername('dispatcher');
    }

    /**
     * @return array{0: object, 1: array<string>, 2: string} client, task IRIs, tour IRI
     */
    private function createTour()
    {
        $jwtManager = self::getContainer()->get(JWTTokenManagerInterface::class);
        $admin = $this->bootstrapAdmin();

        $date = new \DateTime('today');

        $tasks = [];
        for ($i = 0; $i < 3; $i++) {
            $tasks[] = $this->createTask($date, $i);
        }
        $this->entityManager->flush();

        $iris = array_map(fn (Task $t) => '/api/tasks/'.$t->getId(), $tasks);

        $client = static::createClient(defaultOptions: [
            'headers' => ['authorization' => 'Bearer '.$jwtManager->create($admin)],
        ]);

        $client->request('POST', '/api/tours', [
            'json' => [
                'name' => 'Test tour',
                'date' => $date->format('Y-m-d'),
                'tasks' => $iris,
            ],
        ]);
        $this->assertResponseStatusCodeSame(201);

        $tourIri = $this->getResponseJson()['@id'];

        return [$client, $iris, $tourIri];
    }

    private function getResponseJson(): array
    {
        return json_decode(static::getClient()->getResponse()->getContent(), true);
    }

    public function testTheVersionIsExposedOnReads()
    {
        [$client, , $tourIri] = $this->createTour();

        $client->request('GET', $tourIri);
        $this->assertResponseStatusCodeSame(200);

        $this->assertArrayHasKey(
            'version',
            $this->getResponseJson(),
            'a client cannot send back a precondition it was never given'
        );
    }

    public function testAStaleIfMatchIsRefused()
    {
        [$client, $iris, $tourIri] = $this->createTour();

        $client->request('GET', $tourIri);
        $version = $this->getResponseJson()['version'];

        // Somebody else gets there first
        $client->request('PUT', $tourIri, [
            'json' => ['tasks' => array_slice($iris, 0, 2)],
        ]);
        $this->assertResponseStatusCodeSame(200);

        // Our client is now working from a version that no longer exists, and
        // its payload would drop the task the other write kept.
        $client->request('PUT', $tourIri, [
            'headers' => ['If-Match' => sprintf('"%d"', $version)],
            'json' => ['tasks' => array_slice($iris, 0, 1)],
        ]);

        $this->assertResponseStatusCodeSame(409);
    }

    /**
     * A header we cannot parse says the client is broken, not that somebody
     * else got there first. The dashboard reacts to a 409 by discarding the
     * dispatcher's edit and refetching, so answering one here would report a
     * client bug as a phantom conflict.
     */
    public function testAMalformedIfMatchIsARequestError()
    {
        [$client, $iris, $tourIri] = $this->createTour();

        foreach (['not-a-version', '"7", "9"', 'W/""'] as $ifMatch) {
            $client->request('PUT', $tourIri, [
                'headers' => ['If-Match' => $ifMatch],
                'json' => ['tasks' => array_slice($iris, 0, 2)],
            ]);

            $this->assertResponseStatusCodeSame(
                400,
                sprintf('If-Match %s should not read as a conflict', $ifMatch)
            );
        }
    }

    public function testACurrentIfMatchIsAccepted()
    {
        [$client, $iris, $tourIri] = $this->createTour();

        $client->request('GET', $tourIri);
        $version = $this->getResponseJson()['version'];

        $client->request('PUT', $tourIri, [
            'headers' => ['If-Match' => sprintf('"%d"', $version)],
            'json' => ['tasks' => array_slice($iris, 0, 2)],
        ]);

        $this->assertResponseStatusCodeSame(200);
    }

    /**
     * Clients that know nothing about preconditions keep working exactly as
     * before, which is what lets the guard ship without a coordinated release
     * of the mobile app.
     */
    public function testAWriteWithoutIfMatchIsStillAccepted()
    {
        [$client, $iris, $tourIri] = $this->createTour();

        $client->request('PUT', $tourIri, [
            'json' => ['tasks' => array_slice($iris, 0, 2)],
        ]);

        $this->assertResponseStatusCodeSame(200);
    }
}
