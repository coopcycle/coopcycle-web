<?php

namespace Tests\AppBundle\MessageHandler;

use AppBundle\CubeJs\TokenFactory;
use AppBundle\Entity\User;
use AppBundle\Message\ExportTasks;
use AppBundle\MessageHandler\ExportTasksHandler;
use AppBundle\Service\RemotePushNotificationManager;
use AppBundle\Utils\PriceFormatter;
use Doctrine\Common\DataFixtures\Purger\ORMPurger;
use Doctrine\ORM\EntityManagerInterface;
use Fidry\AliceDataFixtures\LoaderInterface;
use Nucleos\UserBundle\Model\UserManager;
use PHPUnit\Framework\TestCase;
use Prophecy\PhpUnit\ProphecyTrait;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;

class ExportTasksHandlerFunctionalTest extends KernelTestCase
{
    use ProphecyTrait;

    public function setUp(): void
    {
        parent::setUp();
        self::bootKernel();

        $this->priceFormatter = self::$container->get(PriceFormatter::class);

        $this->entityManager = self::$container->get(EntityManagerInterface::class);
        $this->handler = new ExportTasksHandler(
            $this->entityManager,
            $this->priceFormatter
        );


        $purger = new ORMPurger($this->entityManager);
        $purger->purge();

        $connection = $this->entityManager->getConnection();
        $rows = $connection->fetchAllAssociative('SELECT sequence_name FROM information_schema.sequences');
        foreach ($rows as $row) {
            $connection->executeQuery(sprintf('ALTER SEQUENCE %s RESTART WITH 1', $row['sequence_name']));
        }

        $this->fixturesLoader = self::$container->get('fidry_alice_data_fixtures.loader.doctrine');
    }

    public function testExport()
    {
        $this->fixturesLoader->load([
            __DIR__.'/../Resources/fixtures/tasks.yml'
        ]);

        $csv = call_user_func_array($this->handler, [ new ExportTasks(new \DateTime('2018-03-01'), new \DateTime('2018-03-03')) ]);

        $this->assertStringEqualsFile(__DIR__.'/../Resources/csv/tasks.csv', $csv);
    }
}
