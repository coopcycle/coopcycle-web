<?php

namespace AppBundle\Utils;


use AppBundle\Entity\Menu\MenuItem;

class CartItem
{
    /**
     * @var MenuItem
     */
    protected $menuItem;

    protected $modifiersDescription;

    protected $quantity;

    protected $modifierChoices;

    protected $key;

    protected $unitPrice;


    public function __construct(MenuItem $menuItem, $quantity, $modifierChoices = [])
    {
        $this->menuItem = $menuItem;
        $this->quantity = (int)$quantity;
        ksort($modifierChoices);
        $this->modifierChoices = $modifierChoices;
        $this->key = $this->getKeyHash();
        $this->setUnitPriceAndDescription();
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

        foreach ($this->modifierChoices as $modifierId => $selectedMenuItems) {

            // get the modifier
            $modifier = $this->menuItem->getModifiers()->filter(function ($element) use ($modifierId) {
                return $element->getId() == $modifierId;
            })->first();

            foreach ($selectedMenuItems as $menuItemId) {

                // get the price for each selected menu item (depends on the Modifier's calculus strategy)
                $menuItem = $modifier->getMenuItemChoices()->filter(
                    function (\AppBundle\Entity\Menu\MenuItem $element) use ($menuItemId) {
                        return $element->getId() == $menuItemId;
                    }
                )->first();

                $unitPrice += $modifier->getSelectedMenuItemPrice($menuItem);
                array_push($modifierNames, $menuItem->getName());
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

}
