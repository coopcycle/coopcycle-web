<?php

namespace AppBundle\EventListener;

use AppBundle\Entity\HubRepository;
use AppBundle\Entity\LocalBusiness;
use AppBundle\Entity\LocalBusinessRepository;
use AppBundle\Entity\Vendor;
use AppBundle\Event\ItemAddedEvent;
use AppBundle\Event\ItemQuantityChangedEvent;
use AppBundle\Event\ItemRemovedEvent;
use AppBundle\Sylius\Order\OrderInterface;

/**
 * This may "upgrade" or "downgrade" the order target,
 * i.e switch from pointing to a single restaurant to pointing to a hub
 */
class CheckoutListener
{
    private $hubRepository;
    private $localBusinessRepository;

    public function __construct(
        HubRepository $hubRepository,
        LocalBusinessRepository $localBusinessRepository)
    {
        $this->hubRepository = $hubRepository;
        $this->localBusinessRepository = $localBusinessRepository;
    }

    private function getRestaurants(OrderInterface $order): \SplObjectStorage
    {
        $restaurants = new \SplObjectStorage();

        foreach ($order->getItems() as $item) {
            $restaurant = $this->localBusinessRepository->findOneByProduct(
                $item->getVariant()->getProduct()
            );

            if ($restaurant && !$restaurants->contains($restaurant)) {
                $restaurants->attach($restaurant);
            }
        }

        return $restaurants;
    }

    public function onItemAdded(ItemAddedEvent $event)
    {
        $order = $event->getOrder();

        $restaurants = $this->getRestaurants($order);

        if (count($restaurants) > 1) {

            $hubs = new \SplObjectStorage();

            foreach ($restaurants as $restaurant) {
                $hub = $this->hubRepository->findOneByRestaurant($restaurant);
                if (!$hubs->contains($hub)) {
                    $hubs->attach($hub);
                }
            }

            if (count($hubs) === 1) {
                $vendor = new Vendor();
                $vendor->setHub($hubs->current());

                $order->setVendor($vendor);
            }
        }
    }

    public function onItemRemoved(ItemRemovedEvent $event)
    {
        $order = $event->getOrder();

        if (!$order->getVendor()->isHub()) {
            return;
        }

        $this->downgrade($order);
    }

    public function onItemQuantityChanged(ItemQuantityChangedEvent $event)
    {
        $order = $event->getOrder();

        if (!$order->getVendor()->isHub()) {
            return;
        }

        $this->downgrade($order);
    }

    private function downgrade(OrderInterface $order)
    {
        $restaurants = $this->getRestaurants($order);

        if (count($restaurants) === 1) {

            $vendor = new Vendor();
            $vendor->setRestaurant($restaurants->current());

            $order->setVendor($vendor);
        }
    }
}
