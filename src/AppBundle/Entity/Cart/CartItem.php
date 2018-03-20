<?php

namespace AppBundle\Entity\Cart;

use AppBundle\Entity\Menu\MenuItem;
use AppBundle\Entity\Menu\Modifier;

class CartItem
{
    /**
     * @var int
     */
    protected $id;

    /**
     * @var MenuItem
     */
    protected $menuItem;

    protected $modifiersDescription;

    protected $quantity;

    protected $modifierChoices;

    protected $key;

    protected $unitPrice;

    protected $cart;

    public function __construct(Cart $cart, MenuItem $menuItem, $quantity, $modifierChoices = [])
    {
        $this->cart = $cart;
        $this->menuItem = $menuItem;
        $this->quantity = (int)$quantity;
        ksort($modifierChoices);
        $this->modifierChoices = $modifierChoices;
        $this->modifiersDescription = '';
        $this->key = $this->getKeyHash();
        $this->setUnitPriceAndDescription();
    }

    /**
     * @return int
     */
    public function getId()
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
     * The hash corresponds to a combination of one MenuItem + it's selected modifiers.
     *
     * @return string
     */
    public function getKeyHash() {
        $key = (string)$this->menuItem->getId();

        $modifier_hash = md5(serialize($this->modifierChoices));

        return $key.'-'.$modifier_hash;

    }

    public function toArray () {
        return [
            'id' => $this->menuItem->getId(),
            'name' => $this->menuItem->getName(),
            'modifiersDescription' => $this->modifiersDescription,
            'quantity' => $this->quantity,
            'key' => $this->key,
            'total' => $this->getTotal(),
            'unitPrice' => $this->unitPrice
        ];
    }

    public function update($quantity) {
        $this->quantity += (int)$quantity;
    }

    public function setUnitPriceAndDescription() {
        $modifierNames = array();
        $unitPrice = $this->menuItem->getPrice();

        foreach ($this->modifierChoices as $modifierId => $selectedModifiers) {

            $menuItemModifier = $this->menuItem->getModifiers()->filter(function ($element) use ($modifierId) {
                return $element->getId() == $modifierId;
            })->first();

            foreach ($selectedModifiers as $modifierId) {

                // get the price for each selected menu item (depends on the Modifier's calculus strategy)
                $modifier = $menuItemModifier->getModifierChoices()->filter(
                    function (Modifier $element) use ($modifierId) {
                        return $element->getId() == $modifierId;
                    }
                )->first();

                $unitPrice += $menuItemModifier->getModifierPrice($modifier);
                array_push($modifierNames, $modifier->getName());
            }
        }

        $this->modifiersDescription = join(", ", $modifierNames);
        $this->unitPrice = $unitPrice;
    }

    public function getTotal() {
        return $this->unitPrice * $this->quantity;
    }

    /**
     * @return string
     */
    public function getKey(): string
    {
        return $this->key;
    }

    /**
     * @param string $key
     */
    public function setKey(string $key)
    {
        $this->key = $key;
    }

    /**
     * @return MenuItem
     */
    public function getMenuItem(): MenuItem
    {
        return $this->menuItem;
    }

    /**
     * @param MenuItem $menuItem
     */
    public function setMenuItem(MenuItem $menuItem)
    {
        $this->menuItem = $menuItem;
    }

    /**
     * @return int
     */
    public function getQuantity(): int
    {
        return $this->quantity;
    }

    /**
     * @param int $quantity
     */
    public function setQuantity(int $quantity)
    {
        $this->quantity = $quantity;
    }

    /**
     * @return mixed
     */
    public function getModifierChoices()
    {
        return $this->modifierChoices;
    }

    /**
     * @param mixed $modifierChoices
     */
    public function setModifierChoices($modifierChoices)
    {
        $this->modifierChoices = $modifierChoices;
    }

    /**
     * @return mixed
     */
    public function getUnitPrice()
    {
        return $this->unitPrice;
    }

    /**
     * @param mixed $unitPrice
     */
    public function setUnitPrice($unitPrice)
    {
        $this->unitPrice = $unitPrice;
    }

    public function setCart(Cart $cart = null)
    {
        $this->cart = $cart;

        return $this;
    }

}
