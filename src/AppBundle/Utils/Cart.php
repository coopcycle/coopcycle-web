<?php

namespace AppBundle\Utils;

use AppBundle\Entity\Restaurant;
use AppBundle\Entity\MenuItem;

class Cart
{
    private $restaurantId;
    private $date;
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

    public function addItem(MenuItem $menuItem)
    {
        foreach ($this->items as $key => $item) {
            if ($item['id'] === $menuItem->getId()) {
                ++$this->items[$key]['quantity'];
                return;
            }
        }
        $this->items[] = array(
            'id' => $menuItem->getId(),
            'name' => $menuItem->getName(),
            'price' => $menuItem->getPrice(),
            'quantity' => 1,
        );
    }

    public function removeItem(MenuItem $menuItem)
    {
        foreach ($this->items as $key => $item) {
            if ($item['id'] === $menuItem->getId()) {
                unset($this->items[$key]);
                break;
            }
        }
    }

    public function getItems()
    {
        // Make sure this is a zero-indexed array, for proper JSON serialization
        return array_values($this->items);
    }

    public function getTotal()
    {
        return array_reduce($this->items, function ($carry, $item) {
            return $carry + ($item['price'] * $item['quantity']);
        }, 0);
    }

    public function getDate()
    {
        return $this->date;
    }

    public function setDate($date)
    {
        $this->date = $date;

        return $this;
    }

    public function toArray()
    {
        return array(
            'date' => $this->date->format('Y-m-d H:i:s'),
            'items' => $this->getItems(),
        );
    }
}
