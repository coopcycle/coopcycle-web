<?php

namespace Tests\AppBundle\Entity\Listener;

use AppBundle\Doctrine\EventSubscriber\TaskSubscriber\TaskListProvider;
use AppBundle\Entity\Delivery;
use AppBundle\Entity\Listener\DeliveryListener;
use AppBundle\Entity\LocalBusiness;
use AppBundle\Entity\Store;
use AppBundle\Entity\Sylius\Order;
use AppBundle\Entity\TourRepository;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\Persistence\Event\LifecycleEventArgs;
use PHPUnit\Framework\TestCase;
use Prophecy\PhpUnit\ProphecyTrait;

class DeliveryListenerTest extends TestCase
{
    use ProphecyTrait;

    private $entityManager;
    private DeliveryListener $listener;

    protected function setUp(): void
    {
        $this->entityManager = $this->prophesize(EntityManagerInterface::class);

        $this->listener = new DeliveryListener(
            $this->entityManager->reveal(),
            $this->prophesize(TaskListProvider::class)->reveal(),
            $this->prophesize(TourRepository::class)->reveal()
        );
    }

    private function prePersist(Delivery $delivery): void
    {
        $this->listener->prePersist(
            $delivery,
            new LifecycleEventArgs($delivery, $this->entityManager->reveal())
        );
    }

    private function createRestaurant(array $tags): LocalBusiness
    {
        $restaurant = new LocalBusiness();
        $restaurant->setTags($tags);

        return $restaurant;
    }

    public function testAppliesStoreTags()
    {
        $store = new Store();
        $store->setTags(['cold']);

        $delivery = new Delivery();
        $delivery->setStore($store);

        $this->prePersist($delivery);

        $this->assertEquals(['cold'], $delivery->getPickup()->getTags());
        $this->assertEquals(['cold'], $delivery->getDropoff()->getTags());
    }

    public function testAppliesRestaurantTags()
    {
        $order = new Order();
        $order->setRestaurant($this->createRestaurant(['fragile', 'cold']));

        $delivery = new Delivery();
        $delivery->setOrder($order);

        $this->prePersist($delivery);

        $this->assertEquals(['fragile', 'cold'], $delivery->getPickup()->getTags());
        $this->assertEquals(['fragile', 'cold'], $delivery->getDropoff()->getTags());
    }

    public function testAppliesTagsOfAllRestaurantsOfMultiVendorOrder()
    {
        $order = new Order();
        $order->addRestaurant($this->createRestaurant(['fragile']));
        $order->addRestaurant($this->createRestaurant(['cold', 'fragile']));

        $delivery = new Delivery();
        $delivery->setOrder($order);

        $this->prePersist($delivery);

        $this->assertEqualsCanonicalizing(['fragile', 'cold'], $delivery->getPickup()->getTags());
        $this->assertEqualsCanonicalizing(['fragile', 'cold'], $delivery->getDropoff()->getTags());
    }

    public function testKeepsExistingTaskTags()
    {
        $order = new Order();
        $order->setRestaurant($this->createRestaurant(['fragile']));

        $delivery = new Delivery();
        $delivery->setOrder($order);
        $delivery->getDropoff()->setTags(['important']);

        $this->prePersist($delivery);

        $this->assertEquals(['fragile'], $delivery->getPickup()->getTags());
        $this->assertEqualsCanonicalizing(['important', 'fragile'], $delivery->getDropoff()->getTags());
    }

    public function testDoesNothingWithoutRestaurant()
    {
        $delivery = new Delivery();
        $delivery->setOrder(new Order());

        $this->prePersist($delivery);

        $this->assertEquals([], $delivery->getPickup()->getTags());
        $this->assertEquals([], $delivery->getDropoff()->getTags());
    }
}
