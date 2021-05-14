<?php

namespace AppBundle\Sylius\Cart;

use AppBundle\Entity\LocalBusiness;
use AppBundle\Entity\LocalBusinessRepository;
use AppBundle\Entity\Vendor;
use AppBundle\Sylius\Order\OrderInterface;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\HttpFoundation\RequestStack;
use Webmozart\Assert\Assert;

class RestaurantResolver
{
    /**
     * @var RequestStack
     */
    private RequestStack $requestStack;

    private $repository;

    private $entityManager;

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
        EntityManagerInterface $entityManager)
    {
        $this->requestStack = $requestStack;
        $this->repository = $repository;
        $this->entityManager = $entityManager;
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
        $data = $this->entityManager
            ->getUnitOfWork()
            ->getOriginalEntityData($cart);

        // This means it is a new object, not persisted yet
        if (!is_array($data) || empty($data)) {
            return true;
        }

        if (!isset($data['vendor'])) {
            throw new \LogicException('No "vendor" key found in original entity data. The column may have been renamed.');
        }

        $restaurant = $this->resolve();

        if (null === $restaurant) {
            throw new \LogicException('No restaurant could be resolved from request.');
        }

        if ($cart->getId() === null) {
            return true;
        }

        Assert::isInstanceOf($data['vendor'], Vendor::class);

        $vendor = $data['vendor'];

        if ($vendor->isHub()) {

            return $vendor->getHub() === $restaurant->getHub();
        }

        if ($vendor->getRestaurant() !== $restaurant) {

            $thisHub = $data['vendor']->getRestaurant()->getHub();
            $thatHub = $restaurant->getHub();

            if (null !== $thisHub && null !== $thatHub && $thisHub === $thatHub) {
                return true;
            }
        }

        return $vendor->getRestaurant() === $restaurant;
    }
}
