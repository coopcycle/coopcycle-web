<?php

namespace AppBundle\Entity\Sylius;

use ApiPlatform\Core\Annotation\ApiResource;
use ApiPlatform\Core\Annotation\ApiProperty;
use AppBundle\Entity\ReusablePackaging;
use AppBundle\Sylius\Customer\CustomerInterface;
use AppBundle\Sylius\Order\AdjustmentInterface;
use AppBundle\Sylius\Order\OrderItemInterface;
use AppBundle\Sylius\Product\ProductVariantInterface;
use Sylius\Component\Order\Model\OrderInterface;
use Sylius\Component\Order\Model\OrderItem as BaseOrderItem;
use Sylius\Component\Order\Model\OrderItemInterface as BaseOrderItemInterface;

#[ApiResource(attributes: ['normalization_context' => ['groups' => ['order']], 'composite_identifier' => false], itemOperations: ['get' => ['method' => 'GET', 'path' => '/orders/{order}/items/{id}']], collectionOperations: [])]
class OrderItem extends BaseOrderItem implements OrderItemInterface
{
    /**
     * @var ProductVariantInterface
     */
    protected $variant;

    /**
     * @var CustomerInterface
     */
    protected $customer;

    /**
     * {@inheritdoc}
     */
    public function getTaxTotal(): int
    {
        $taxTotal = 0;

        foreach ($this->getAdjustments(AdjustmentInterface::TAX_ADJUSTMENT) as $taxAdjustment) {
            $taxTotal += $taxAdjustment->getAmount();
        }

        return $taxTotal;
    }

    /**
     * {@inheritdoc}
     */
    public function getVariant(): ?ProductVariantInterface
    {
        return $this->variant;
    }

    /**
     * {@inheritdoc}
     */
    public function setVariant(?ProductVariantInterface $variant): void
    {
        $this->variant = $variant;
    }

    /**
     * {@inheritdoc}
     */
    public function equals(BaseOrderItemInterface $item): bool
    {
        return parent::equals($item) || ($item instanceof static && $item->getVariant() === $this->variant && $item->getCustomer() === $this->getCustomer());
    }

    public function getCustomer(): ?CustomerInterface
    {
        return $this->customer;
    }

    public function setCustomer(?CustomerInterface $customer): void
    {
        $this->customer = $customer;
    }

    #[ApiProperty(identifier: true)]
    public function getOrder(): ?OrderInterface
    {
        return parent::getOrder();
    }

    public function hasOverridenLoopeatQuantityForPackaging(ReusablePackaging $packaging): bool
    {
        $data = $packaging->getData();
        $deliver = $this->getOrder()->getLoopeatDeliver();

        if (isset($deliver[$this->getId()])) {
            foreach ($deliver[$this->getId()] as $format) {
                if ($format['format_id'] === $data['id']) {

                    return true;
                }
            }
        }

        return false;
    }

    public function getOverridenLoopeatQuantityForPackaging(ReusablePackaging $packaging)
    {
        $data = $packaging->getData();
        $deliver = $this->getOrder()->getLoopeatDeliver();

        if (isset($deliver[$this->getId()])) {
            foreach ($deliver[$this->getId()] as $format) {
                if ($format['format_id'] === $data['id']) {

                    return $format['quantity'];
                }
            }
        }
    }
}
