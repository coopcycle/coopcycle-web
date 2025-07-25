<?php

namespace Tests\AppBundle\Api;

use ApiPlatform\Symfony\Bundle\Test\ApiTestCase;
use AppBundle\Fixtures\DatabasePurger;
use Doctrine\ORM\EntityManagerInterface;
use Fidry\AliceDataFixtures\Persistence\PurgeMode;
use Lexik\Bundle\JWTAuthenticationBundle\Services\JWTTokenManagerInterface;
use Nucleos\UserBundle\Util\UserManipulator;
use Nucleos\UserBundle\Model\UserManager as UserManagerInterface;

/**
 * https://symfony.com/doc/5.x/testing/profiling.html
 */
class TasksFunctionalTest extends ApiTestCase
{
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

        /** @see https://joeymasip.medium.com/symfony-phpunit-testing-database-data-322383ed0603 */
        $this->entityManager->close();
        $this->entityManager = null;
    }

    public function testQueryCountIsStable()
    {
        $userManipulator = self::getContainer()->get(UserManipulator::class);

        $jwtManager = self::getContainer()->get(JWTTokenManagerInterface::class);

        $userManager = self::getContainer()->get(UserManagerInterface::class);

        $fixturesLoader = self::getContainer()->get('fidry_alice_data_fixtures.loader.doctrine');

        $fixturesLoader->load([
            __DIR__.'/../../../fixtures/ORM/settings_mandatory.yml',
            __DIR__.'/../../../fixtures/ORM/sylius_channels.yml',
        ], $_SERVER);

        $fixturesLoader->load([
            __DIR__.'/../../../features/fixtures/ORM/dispatch_with_packages.yml',
        ], $_SERVER, [], PurgeMode::createNoPurgeMode());

        $userManipulator->addRole('sarah', 'ROLE_ADMIN');
        $user = $userManager->findUserByUsername('sarah');

        $token = $jwtManager->create($user);

        $client = static::createClient(defaultOptions: [
            'headers' => [
                'authorization' => 'Bearer '.$token
            ]
        ]);

        // enable the profiler only for the next request (if you make
        // new requests, you must call this method again)
        // (it does nothing if the profiler is not available)
        $client->enableProfiler();

        $response = $client->request('GET', '/api/tasks?date=2024-12-01');

        $this->assertResponseStatusCodeSame(200);

        $profile = $client->getProfile();

        $this->assertLessThanOrEqual(
            9,
            $profile->getCollector('db')->getQueryCount()
        );
    }
}
