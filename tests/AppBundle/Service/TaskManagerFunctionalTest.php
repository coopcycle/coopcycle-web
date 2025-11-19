<?php

namespace AppBundle\Service;

use AppBundle\Entity\Sylius\Order;
use AppBundle\Entity\Task;
use Doctrine\ORM\EntityManagerInterface;
use Fidry\AliceDataFixtures\LoaderInterface;
use Fidry\AliceDataFixtures\Persistence\PurgeMode;
use Sylius\Component\Order\Model\OrderInterface;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;

class TaskManagerFunctionalTest extends KernelTestCase
{
    protected ?EntityManagerInterface $entityManager;
    protected TaskManager $taskManager;
    protected LoaderInterface $fixturesLoader;

    protected function setUp(): void
    {
        parent::setUp();
        self::bootKernel();
        $this->entityManager = self::getContainer()->get(EntityManagerInterface::class);
        $this->taskManager = self::getContainer()->get(TaskManager::class);
        $this->fixturesLoader = self::getContainer()->get('fidry_alice_data_fixtures.loader.doctrine');
    }

    protected function tearDown(): void
    {
        parent::tearDown();
        /** @see https://joeymasip.medium.com/symfony-phpunit-testing-database-data-322383ed0603 */
        $this->entityManager->close();
        $this->entityManager = null;
    }

    function testOnWithLastCancelledTask()
    {
        // SETUP
        $entities = $this->fixturesLoader->load([
            __DIR__.'/../../../fixtures/ORM/task_manager_one_non_cancelled.yml',
        ], $_SERVER, [], PurgeMode::createDeleteMode());

        /** @var Task $task */
        $task = $entities['task_1'];

        /** @var Order $order */
        $order = $entities['order_1'];

        $this->assertEquals(OrderInterface::STATE_NEW, $order->getState());

        // Cancel the order
        $this->taskManager->cancel($task);
        $this->entityManager->flush();

        // Assert that linked order is cancelled
        $this->assertEquals(OrderInterface::STATE_CANCELLED, $order->getState());
    }

    function testOnWithFirstCancelledTask()
    {
        // SETUP
        $entities = $this->fixturesLoader->load([
            __DIR__.'/../../../fixtures/ORM/setup_default.yml',
            __DIR__.'/../../../fixtures/ORM/user_dispatcher.yml',
            __DIR__.'/../../../fixtures/ORM/store_with_task_pricing.yml',
            __DIR__.'/../../../fixtures/ORM/package_delivery_order_multi_dropoff.yml',
        ], $_SERVER, [], PurgeMode::createDeleteMode());

        /** @var Task $task */
        $task = $entities['task_1'];
        /** @var Order $order */
        $order = $entities['order_1'];

        $this->assertEquals(OrderInterface::STATE_NEW, $order->getState());
        $this->assertEquals(899, $order->getTotal());

        // Cancel the order
        $this->taskManager->cancel($task);
        $this->entityManager->flush();

        // Assert that linked order is NOT cancelled
        $this->assertEquals(OrderInterface::STATE_NEW, $order->getState());
        // Assert that price has been recalculated
        $this->assertEquals(400, $order->getTotal());
    }

    function testOnWithOnlyOneCancelledTask()
    {
        // SETUP
        $entities = $this->fixturesLoader->load([
            __DIR__.'/../../../fixtures/ORM/task_manager_single_task.yml',
        ], $_SERVER, [], PurgeMode::createDeleteMode());

        /** @var Task $task */
        $task = $entities['task_1'];
        /** @var Order $order */
        $order = $entities['order_1'];

        $this->assertEquals(OrderInterface::STATE_NEW, $order->getState());

        // Cancel the order
        $this->taskManager->cancel($task);
        $this->entityManager->flush();

        // Assert that linked order is cancelled
        $this->assertEquals(OrderInterface::STATE_CANCELLED, $order->getState());
    }

    function testOnWithFirstCancelledTaskArbitraryPrice()
    {
        // SETUP
        $entities = $this->fixturesLoader->load([
            __DIR__.'/../../../fixtures/ORM/setup_default.yml',
            __DIR__.'/../../../fixtures/ORM/user_dispatcher.yml',
            __DIR__.'/../../../fixtures/ORM/store_without_pricing.yml',
            __DIR__.'/../../../fixtures/ORM/package_delivery_order_multi_dropoff.yml',
        ], $_SERVER, [], PurgeMode::createDeleteMode());

        /** @var Task $task */
        $task = $entities['task_1'];
        /** @var Order $order */
        $order = $entities['order_1'];

        $this->assertEquals(OrderInterface::STATE_NEW, $order->getState());
        $oldTotal = $order->getTotal();

        // Cancel the order
        $this->taskManager->cancel($task);
        $this->entityManager->flush();

        // Assert that linked order is NOT cancelled
        $this->assertEquals(OrderInterface::STATE_NEW, $order->getState());
        // Assert that the arbitrary price has NOT been recalculated
        $this->assertEquals($oldTotal, $order->getTotal());
    }

    function testOnWithFirstCancelledTaskWithPricePerDistance()
    {
        // SETUP
        $entities = $this->fixturesLoader->load([
            __DIR__.'/../../../fixtures/ORM/setup_default.yml',
            __DIR__.'/../../../fixtures/ORM/user_dispatcher.yml',
            __DIR__.'/../../../fixtures/ORM/store_w_distance_pricing.yml',
            __DIR__.'/../../../fixtures/ORM/package_delivery_order_multi_dropoff.yml',
        ], $_SERVER, [], PurgeMode::createDeleteMode());

        /** @var Task $task */
        $task = $entities['task_2'];
        /** @var Order $order */
        $order = $entities['order_1'];

        $this->assertEquals(OrderInterface::STATE_NEW, $order->getState());
        $this->assertEquals(600, $order->getTotal());

        // Cancel the task
        $this->taskManager->cancel($task);
        $this->entityManager->flush();

        // Assert that linked order is NOT cancelled
        $this->assertEquals(OrderInterface::STATE_NEW, $order->getState());
        // Assert that price has been recalculated
        $this->assertEquals(400, $order->getTotal());
    }
}
