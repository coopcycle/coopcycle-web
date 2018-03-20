<?php

namespace AppBundle\Entity;

use AppBundle\Entity\Menu\MenuItemModifier;
use Symfony\Component\Serializer\Annotation\Groups;
use ApiPlatform\Core\Annotation\ApiResource;

/**
 * Class OrderItemModifier
 *
 * Links between an OrderItem, a Modifier and a MenuItem (= selected item)
 *
 * @ApiResource(
 *   attributes={
 *     "denormalization_context"={"groups"={"order"}},
 *     "normalization_context"={"groups"={"order"}}
 * })
 * @package AppBundle\Entity
 *
 */
class OrderItemModifier
{
    /**
     * @var int
     */
    private $id;

    /**
     * @var OrderItem
     */
    private $orderItem;

    /**
     * Name of the selected modifier
     *
     * @Groups({"order"})
     */
    private $name;


    /**
     * Description of the selected modifier
     *
     * @Groups({"order"})
     */
    private $description;

    /**
     * Additional price
     *
     * @var float
     *
     */
    private $additionalPrice;

    /**
     * @var MenuItemModifier
     *
     * @Groups({"order"})
     */
    private $modifier;

    /**
     * @return int
     */
    public function getId(): int
    {
        return $this->id;
    }

    /**
     * @param int $id
     */
    public function setId(int $id)
    {
        $this->id = $id;
    }

    /**
     * @return OrderItem
     */
    public function getOrderItem()
    {
        return $this->orderItem;
    }

    /*
     * @param OrderItem $orderItem
     */
    public function setOrderItem($orderItem)
    {
        $this->orderItem = $orderItem;
    }

    /**
     * @return mixed
     */
    public function getName()
    {
        return $this->name;
    }

    /**
     * @param mixed $name
     */
    public function setName($name)
    {
        $this->name = $name;
    }

    /**
     * @return mixed
     */
    public function getDescription()
    {
        return $this->description;
    }

    /**
     * @param mixed $description
     */
    public function setDescription($description)
    {
        $this->description = $description;
    }

    /**
     * @return float
     */
    public function getAdditionalPrice(): float
    {
        return $this->additionalPrice;
    }

    /**
     * @param float $additionalPrice
     */
    public function setAdditionalPrice(float $additionalPrice)
    {
        $this->additionalPrice = $additionalPrice;
    }

    /**
     * @return MenuItemModifier
     */
    public function getModifier()
    {
        return $this->modifier;
    }

    /**
     * @param MenuItemModifier $modifier
     */
    public function setModifier(MenuItemModifier $modifier)
    {
        $this->modifier = $modifier;
    }

}
