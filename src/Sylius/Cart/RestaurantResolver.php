<?php

namespace AppBundle\Sylius\Cart;

use Symfony\Component\HttpFoundation\RequestStack;

class RestaurantResolver
{
    /**
     * @var RequestStack
     */
    private RequestStack $requestStack;

    private static $routes = [
        'restaurant',
        'restaurant_cart_address',
        'restaurant_add_product_to_cart',
        'restaurant_cart_clear_time',
        'restaurant_modify_cart_item_quantity',
        'restaurant_remove_from_cart',
    ];

    /**
     * @param RequestStack $requestStack
     */
    public function __construct(RequestStack $requestStack)
    {
        $this->requestStack = $requestStack;
    }

    /**
     * @return int|null
     */
    public function resolve(): ?int
    {
        $request = $this->requestStack->getMasterRequest();

        if (!$request) {

            return null;
        }

        if (!in_array($request->attributes->get('_route'), self::$routes)) {

            return null;
        }

        return $request->attributes->getInt('id');
    }
}
