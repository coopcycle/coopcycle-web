<?php

namespace AppBundle\Utils;

use AppBundle\Entity\Restaurant;
use AppBundle\Entity\Product;

class Cart
{
    private $restaurantId;
    private $items = array();

    public function __construct(Restaurant $restaurant = null)
    {
        if (null !== $restaurant) {
            $this->restaurantId = $restaurant->getId();
        }
    }

    public function isForRestaurant(Restaurant $restaurant)
    {
        return $this->restaurantId === $restaurant->getId();
    }

    public function getRestaurantId()
    {
        return $this->restaurantId;
    }

    public function clear()
    {
        $this->items = array();
    }

    public function addProduct(Product $product)
    {
        foreach ($this->items as $key => $item) {
            if ($item['id'] === $product->getId()) {
                ++$this->items[$key]['quantity'];
                return;
            }
        }
        $this->items[] = array(
            'id' => $product->getId(),
            'name' => $product->getName(),
            'price' => $product->getPrice(),
            'quantity' => 1,
        );
    }

    public function getItems()
    {
        return $this->items;
    }

    public function getTotal()
    {
        return array_reduce($this->items, function($carry , $item) {
            return $carry + $item['price'];
        }, 0);
    }

    public function toArray()
    {
        return array(
            'items' => $this->items,
        );
    }
}