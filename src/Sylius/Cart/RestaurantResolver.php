<?php

namespace AppBundle\Sylius\Cart;

use AppBundle\Entity\LocalBusiness;
use AppBundle\Entity\LocalBusinessRepository;
use AppBundle\Sylius\Order\OrderInterface;
use Psr\Log\LoggerInterface;
use Psr\Log\NullLogger;
use Symfony\Component\HttpFoundation\RequestStack;
use Webmozart\Assert\Assert;

class RestaurantResolver
{
    /**
     * @var RequestStack
     */
    private RequestStack $requestStack;

    private $repository;


    private static $routes = [
        'restaurant',
        'restaurant_cart_address',
        'restaurant_add_product_to_cart',
        'restaurant_cart_clear_time',
        'restaurant_modify_cart_item_quantity',
        'restaurant_remove_from_cart',
        'restaurant_cart',
    ];

    /**
     * @param RequestStack $requestStack
     * @param LocalBusinessRepository $repository
     */
    public function __construct(
        RequestStack $requestStack,
        LocalBusinessRepository $repository,
        LoggerInterface $logger = null)
    {
        $this->requestStack = $requestStack;
        $this->repository = $repository;
        $this->logger = $logger ?? new NullLogger();
    }

    /**
     * @return LocalBusiness|null
     */
    public function resolve(): ?LocalBusiness
    {
        $request = $this->requestStack->getMasterRequest();

        if (!$request) {

            return null;
        }

        if (!in_array($request->attributes->get('_route'), self::$routes)) {

            return null;
        }

        return $this->repository->find(
            $request->attributes->getInt('id')
        );
    }

    /**
     * @return bool
     */
    public function accept(OrderInterface $cart): bool
    {
        $restaurant  = $this->resolve();
        $restaurants = $cart->getRestaurants();

        if (count($restaurants) === 0) {
            $this->logger->debug('Cart is empty, accepting');
            return true;
        }

        if ($restaurants->contains($restaurant)) {
            $this->logger->debug('Cart contains restaurant, accepting');
            return true;
        }

        if (null !== $cart->getBusinessAccount()) {
            return $cart->getBusinessAccount()->getBusinessRestaurantGroup()->getRestaurants()->contains($restaurant);
        }

        $hub = $restaurants->first()->getHub();

        if (null === $hub) {

            return $restaurants->first() === $restaurant;
        }

        return $hub === $restaurant->getHub();
    }
}
