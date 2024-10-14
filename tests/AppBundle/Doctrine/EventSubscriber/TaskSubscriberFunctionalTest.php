<?php

namespace Tests\AppBundle\Doctrine\EventSubscriber;

use AppBundle\Entity\Address;
use AppBundle\Entity\Base\GeoCoordinates;
use AppBundle\Entity\Delivery;
use AppBundle\Entity\Sylius\Order;
use AppBundle\Entity\Task;
use AppBundle\Service\TaskManager;
use Doctrine\Common\DataFixtures\Purger\ORMPurger;
use Doctrine\ORM\EntityManagerInterface;
use Fidry\AliceDataFixtures\LoaderInterface;
use Sylius\Component\Order\Model\OrderInterface;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;

class TaskSubscriberFunctionalTest extends KernelTestCase
{
    protected ?EntityManagerInterface $entityManager;
    protected TaskManager $taskManager;

    protected function setUp(): void
    {
        parent::setUp();
        self::bootKernel();
        $this->entityManager = self::$container->get(EntityManagerInterface::class);
        $this->taskManager = self::$container->get(TaskManager::class);

        $purger = new ORMPurger($this->entityManager);
        $purger->purge();
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
        $address = new Address();
        $address->setStreetAddress("64 rue de la paix, France");
        $address->setGeo(new GeoCoordinates(1.0, 1.0));
        $this->entityManager->persist($address);

        $task = new Task();
        $task->setStatus(Task::STATUS_TODO);
        $task->setBefore(new \DateTime());
        $task->setAddress($address);
        $this->entityManager->persist($task);

        $task2 = new Task();
        $task2->setStatus(Task::STATUS_CANCELLED);
        $task2->setBefore(new \DateTime());
        $task2->setAddress($address);
        $this->entityManager->persist($task2);

        $delivery = new Delivery();
        $delivery->setTasks([$task, $task2]);
        $this->entityManager->persist($delivery);

        $order = new Order();
        $order->setState(OrderInterface::STATE_NEW);
        $order->setDelivery($delivery);
        $this->entityManager->persist($order);
        $this->entityManager->flush();

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
        $address = new Address();
        $address->setStreetAddress("64 rue de la paix, France");
        $address->setGeo(new GeoCoordinates(1.0, 1.0));
        $this->entityManager->persist($address);

        $task = new Task();
        $task->setStatus(Task::STATUS_TODO);
        $task->setBefore(new \DateTime());
        $task->setAddress($address);
        $this->entityManager->persist($task);

        $task2 = new Task();
        $task2->setStatus(Task::STATUS_TODO);
        $task2->setBefore(new \DateTime());
        $task2->setAddress($address);
        $this->entityManager->persist($task2);

        $delivery = new Delivery();
        $delivery->setTasks([$task, $task2]);
        $this->entityManager->persist($delivery);

        $order = new Order();
        $order->setState(OrderInterface::STATE_NEW);
        $order->setDelivery($delivery);
        $this->entityManager->persist($order);
        $this->entityManager->flush();

        $this->assertEquals(OrderInterface::STATE_NEW, $order->getState());

        // Cancel the order
        $this->taskManager->cancel($task);
        $this->entityManager->flush();

        // Assert that linked order is cancelled
        $this->assertEquals(OrderInterface::STATE_NEW, $order->getState());
    }

    function testOnWithOnlyOneCancelledTask()
    {
        // SETUP
        $address = new Address();
        $address->setStreetAddress("64 rue de la paix, France");
        $address->setGeo(new GeoCoordinates(1.0, 1.0));
        $this->entityManager->persist($address);

        $task = new Task();
        $task->setStatus(Task::STATUS_TODO);
        $task->setBefore(new \DateTime());
        $task->setAddress($address);
        $this->entityManager->persist($task);

        $delivery = new Delivery();
        $delivery->setTasks([$task]);
        $this->entityManager->persist($delivery);

        $order = new Order();
        $order->setState(OrderInterface::STATE_NEW);
        $order->setDelivery($delivery);
        $this->entityManager->persist($order);
        $this->entityManager->flush();

        $this->assertEquals(OrderInterface::STATE_NEW, $order->getState());

        // Cancel the order
        $this->taskManager->cancel($task);
        $this->entityManager->flush();

        // Assert that linked order is cancelled
        $this->assertEquals(OrderInterface::STATE_CANCELLED, $order->getState());
    }
}
