<?php

namespace AppBundle\Enum;

use MyCLabs\Enum\Enum;

/**
 * @see https://schema.org/FoodEstablishment
 */
class FoodEstablishment extends Enum
{
    const BAKERY               = 'http://schema.org/Bakery';
    const BAR_OR_PUB           = 'http://schema.org/BarOrPub';
    const BREWERY              = 'http://schema.org/Brewery';
    const CAFE_OR_COFFEE_SHOP  = 'http://schema.org/CafeOrCoffeeShop';
    const DISTILLERY           = 'http://schema.org/Distillery';
    const FAST_FOOD_RESTAURANT = 'http://schema.org/FastFoodRestaurant';
    const ICE_CREAM_SHOP       = 'http://schema.org/IceCreamShop';
    const RESTAURANT           = 'http://schema.org/Restaurant';
    const WINERY               = 'http://schema.org/Winery';
}
