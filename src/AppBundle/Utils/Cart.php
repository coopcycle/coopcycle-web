<?php

namespace AppBundle\Utils;

use AppBundle\Entity\Address;
use AppBundle\Entity\Menu\MenuItem;
use AppBundle\Entity\Restaurant;
use AppBundle\Validator\Constraints\IsValidDeliveryDate;
use Symfony\Component\HttpKernel\Exception\ServiceUnavailableHttpException;
use Symfony\Component\Validator\Constraint;
use Symfony\Component\Validator\Constraints as Assert;
use Symfony\Component\Validator\Context\ExecutionContextInterface;

class AddProductException extends \Exception {}

class UnavailableProductException extends AddProductException {}

class RestaurantMismatchException extends AddProductException {}


/**
 * Class Cart
 * @package AppBundle\Utils
 *
 * @IsValidDeliveryDate(groups="cart")
 */
class Cart
{

    /**
     *
     * Restaurant the cart is linked to
     *
     * @var Restaurant
     */
    private $restaurant;

    /**
     * Delivery address for the cart
     *
     * @var Address
     */
    private $address;

    /**
     * Distance to deliver for the cart
     *
     * @var int
     */
    private $distance;

    /**
     * Delivery date for the cart
     *
     * @var string
     */
    private $date;

    /**
     * @var CartItem[]
     */
    private $items = array();

    /**
     * Cart constructor.
     * @param Restaurant|null $restaurant
     */
    public function __construct(Restaurant $restaurant = null)
    {
        $this->restaurant = $restaurant;
        $this->address = new Address();
    }

    public function isForRestaurant(Restaurant $restaurant)
    {
        return $this->getRestaurantId() === $restaurant->getId();
    }

    public function getRestaurantId()
    {
        if (!is_null($this->restaurant)) {
            return $this->restaurant->getId();
        }
    }

    public function getRestaurant() {
        return $this->restaurant;
    }

    public function clear()
    {
        $this->items = array();
    }

    public function addItem(MenuItem $menuItem, $quantity = 1, $modifierChoices = [])
    {
        if (!$menuItem->getIsAvailable()) {
            throw new UnavailableProductException(
                sprintf('Product %s is not available', $menuItem->getId())
            );
        }

        if ($this->getRestaurantId() && $menuItem->getRestaurant()->getId() != $this->getRestaurantId()) {
            throw new RestaurantMismatchException(
                sprintf('Product %s doesn\'t belong to restaurant %s', $menuItem->getId(), $this->getRestaurantId())
            );
        }

        $cartItem = new CartItem($menuItem, $quantity, $modifierChoices);
        $itemKey = $cartItem->getKey();

        if (array_key_exists($itemKey, $this->items)) {
            $this->items[$itemKey]->update($quantity);
        }
        else {
            $this->items[$itemKey] = $cartItem;
        }

        return $this->items;

    }

    public function removeItem($itemKey)
    {
        if (array_key_exists($itemKey, $this->items)) {
            unset($this->items[$itemKey]);
        }

        return $this->items;
    }

    public function getItems()
    {
        return $this->items;
    }

    public function getTotal()
    {
        if (count($this->items) === 0) {
            return 0;
        }

        $itemsTotal = array_reduce($this->items, function ($carry, $item) {
            return $carry + $item->getTotal();
        }, 0);

        return $itemsTotal + $this->restaurant->getFlatDeliveryPrice();
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

    /**
     * @return int
     */
    public function getDistance()
    {
        return $this->distance;
    }

    /**
     * @param int $distance
     */
    public function setDistance(int $distance)
    {
        $this->distance = $distance;
    }

    /**
     * @return Address
     */
    public function getAddress(): Address
    {
        return $this->address;
    }

    /**
     * @param Address $address
     */
    public function setAddress(Address $address)
    {
        $this->address = $address;
    }


    public function getNormalizedItems()
    {
        // Make sure this is a zero-indexed array, for proper JSON serialization
        return array_values(array_map(function ($el) { return $el->toArray(); }, $this->items));
    }

    public function toArray()
    {
        return array(
            'date' => $this->date->format('Y-m-d H:i:s'),
            'items' => $this->getNormalizedItems(),
        );
    }

    /**
     * Custom order validation.
     * @Assert\Callback(groups={"cart"})
     */
    public function validate(ExecutionContextInterface $context, $payload)
    {
        // Validate distance
        if (!is_null($this->address->getGeo()) && !is_null($this->restaurant)) {
            $maxDistance = $this->getRestaurant()->getMaxDistance();

            $constraint = new Assert\LessThan(['value' => $maxDistance]);
            $context
                ->getValidator()
                ->inContext($context)
                ->atPath('distance')
                ->validate($this->distance, $constraint, [Constraint::DEFAULT_GROUP]);
        }
    }
}
