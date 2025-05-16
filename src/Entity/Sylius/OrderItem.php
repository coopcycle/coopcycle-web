<?php

namespace AppBundle\Entity\Sylius;

use ApiPlatform\Metadata\Link;
use ApiPlatform\Metadata\Get;
use ApiPlatform\Metadata\Post;
use ApiPlatform\Metadata\ApiResource;
use ApiPlatform\Metadata\ApiProperty;
use ApiPlatform\Metadata\ApiFilter;
use AppBundle\Api\Dto\CartItemInput;
use AppBundle\Api\State\CartItemProcessor;
use AppBundle\Entity\ReusablePackaging;
use AppBundle\Sylius\Customer\CustomerInterface;
use AppBundle\Sylius\Order\AdjustmentInterface;
use AppBundle\Sylius\Order\OrderItemInterface;
use AppBundle\Sylius\Product\ProductVariantInterface;
use Sylius\Component\Order\Model\OrderInterface;
use Sylius\Component\Order\Model\OrderItem as BaseOrderItem;
use Sylius\Component\Order\Model\OrderItemInterface as BaseOrderItemInterface;

#[ApiResource(
    uriTemplate: '/orders/{order}/items/{id}',
    operations: [
        new Get()
    ],
    uriVariables: [
        'order' => new Link(fromClass: Order::class, toProperty: 'order'),
        'id' => new Link(fromClass: self::class),
    ],
    normalizationContext: ['groups' => ['order']],
)]
// https://github.com/api-platform/api-platform/issues/571#issuecomment-1473665701
#[ApiResource(
    uriTemplate: '/orders/{id}/items',
    operations: [
        new Post(
            openapiContext: ['summary' => 'Adds items to a Order resource.'],
            normalizationContext: ['groups' => ['cart']],
            denormalizationContext: ['groups' => ['cart']],
            validationContext: ['groups' => ['cart']],
            input: CartItemInput::class,
            read: false,
            // FIXME Implement security
            // security: 'is_granted(\'edit\', object)',
            processor: CartItemProcessor::class
        )
    ],
    uriVariables: [
        'id' => new Link(fromClass: Order::class, fromProperty: 'items')
    ]
)]
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
