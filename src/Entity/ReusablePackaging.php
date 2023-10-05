<?php

namespace AppBundle\Entity;

use Symfony\Component\Serializer\Annotation\Groups;
use Sylius\Component\Inventory\Model\StockableInterface;
use Symfony\Contracts\Translation\TranslatorInterface;

class ReusablePackaging implements StockableInterface
{
    public const TYPE_INTERNAL = 'internal';
    public const TYPE_LOOPEAT = 'loopeat';
    public const TYPE_DABBA = 'dabba';
    public const TYPE_VYTAL = 'vytal';

    private $id;

    protected $restaurant;

    protected $price = 0;

    /**
     * @Groups({"restaurant"})
     */
    protected $name;

    protected $onHold = 0;

    protected $onHand = 0;

    protected $tracked = false;

    /**
     * @Groups({"restaurant"})
     */
    protected $type = self::TYPE_INTERNAL;

    /**
     * @Groups({"restaurant"})
     */
    protected $data = [];

    public function getId()
    {
        return $this->id;
    }

    public function getRestaurant()
    {
        return $this->restaurant;
    }

    public function setRestaurant(LocalBusiness $restaurant)
    {
        $this->restaurant = $restaurant;

        return $this;
    }

    public function getPrice()
    {
        return $this->price;
    }

    public function setPrice($price)
    {
        $this->price = $price;

        return $this;
    }

    public function getName()
    {
        return $this->name;
    }

    public function setName($name)
    {
        $this->name = $name;

        return $this;
    }

    /**
     * {@inheritdoc}
     */
    public function isInStock(): bool
    {
        return 0 < $this->onHand;
    }

    /**
     * {@inheritdoc}
     */
    public function getOnHold(): ?int
    {
        return $this->onHold;
    }

    /**
     * {@inheritdoc}
     */
    public function setOnHold(?int $onHold): void
    {
        $this->onHold = $onHold;
    }

    /**
     * {@inheritdoc}
     */
    public function getOnHand(): ?int
    {
        return $this->onHand;
    }

    /**
     * {@inheritdoc}
     */
    public function setOnHand(?int $onHand): void
    {
        $this->onHand = (0 > $onHand) ? 0 : $onHand;
    }

    /**
     * {@inheritdoc}
     */
    public function isTracked(): bool
    {
        return $this->tracked;
    }

    /**
     * {@inheritdoc}
     */
    public function setTracked(bool $tracked): void
    {
        $this->tracked = $tracked;
    }

    /**
     * {@inheritdoc}
     */
    public function getInventoryName(): ?string
    {
        return $this->getRestaurant()->getName();
    }

    /**
     * @return array
     */
    public function getData(): array
    {
        return $this->data;
    }

    /**
     * @param array $data
     */
    public function setData(array $data = [])
    {
        $this->data = $data;
    }

    public function getType()
    {
        return $this->type;
    }

    public function setType($type)
    {
        $this->type = $type;

        return $this;
    }

    public function getAdjustmentLabel(TranslatorInterface $translator, int $units): string
    {
        if ($this->type === self::TYPE_LOOPEAT) {
            return sprintf('%s × %s', $units, $this->getName());
        }

        return $translator->trans('order_item.adjustment_type.reusable_packaging', [
            '%quantity%' => $units,
        ]);
    }
}
