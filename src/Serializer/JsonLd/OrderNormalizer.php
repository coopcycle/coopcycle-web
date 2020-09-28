<?php

namespace AppBundle\Serializer\JsonLd;

use ApiPlatform\Core\Api\IriConverterInterface;
use ApiPlatform\Core\JsonLd\Serializer\ItemNormalizer;
use AppBundle\Entity\Sylius\Order;
use AppBundle\Sylius\Order\AdjustmentInterface;
use AppBundle\Sylius\Product\LazyProductVariantResolverInterface;
use AppBundle\Utils\DateUtils;
use AppBundle\Utils\PriceFormatter;
use Sylius\Component\Channel\Context\ChannelContextInterface;
use Sylius\Bundle\PromotionBundle\Doctrine\ORM\PromotionCouponRepository;
use Sylius\Component\Order\Model\AdjustmentInterface as BaseAdjustmentInterface;
use Sylius\Component\Order\Modifier\OrderItemQuantityModifierInterface;
use Sylius\Component\Order\Modifier\OrderModifierInterface;
use Sylius\Component\Order\Processor\OrderProcessorInterface;
use Sylius\Component\Product\Repository\ProductRepositoryInterface;
use Sylius\Component\Resource\Factory\FactoryInterface;
use Sylius\Component\Resource\Repository\RepositoryInterface;
use Symfony\Contracts\Translation\TranslatorInterface;
use Symfony\Component\Serializer\Normalizer\DenormalizerInterface;
use Symfony\Component\Serializer\Normalizer\NormalizerInterface;
use Symfony\Component\Serializer\Normalizer\ObjectNormalizer;

class OrderNormalizer implements NormalizerInterface, DenormalizerInterface
{
    private $normalizer;
    private $channelContext;
    private $productRepository;
    private $productOptionValueRepository;
    private $variantResolver;
    private $orderItemFactory;
    private $orderItemQuantityModifier;
    private $orderModifier;
    private $promotionCouponRepository;
    private $orderProcessor;
    private $priceFormatter;
    private $translator;

    public function __construct(
        ItemNormalizer $normalizer,
        ObjectNormalizer $objectNormalizer,
        ChannelContextInterface $channelContext,
        ProductRepositoryInterface $productRepository,
        RepositoryInterface $productOptionValueRepository,
        LazyProductVariantResolverInterface $variantResolver,
        FactoryInterface $orderItemFactory,
        OrderItemQuantityModifierInterface $orderItemQuantityModifier,
        OrderModifierInterface $orderModifier,
        IriConverterInterface $iriConverter,
        PromotionCouponRepository $promotionCouponRepository,
        OrderProcessorInterface $orderProcessor,
        PriceFormatter $priceFormatter,
        TranslatorInterface $translator)
    {
        $this->normalizer = $normalizer;
        $this->channelContext = $channelContext;
        $this->productRepository = $productRepository;
        $this->productOptionValueRepository = $productOptionValueRepository;
        $this->variantResolver = $variantResolver;
        $this->orderItemFactory = $orderItemFactory;
        $this->orderItemQuantityModifier = $orderItemQuantityModifier;
        $this->orderModifier = $orderModifier;
        $this->objectNormalizer = $objectNormalizer;
        $this->iriConverter = $iriConverter;
        $this->promotionCouponRepository = $promotionCouponRepository;
        $this->orderProcessor = $orderProcessor;
        $this->priceFormatter = $priceFormatter;
        $this->translator = $translator;
    }

    public function normalizeAdjustments(Order $order)
    {
        $serializeAdjustment = function (BaseAdjustmentInterface $adjustment) {

            return [
                'id' => $adjustment->getId(),
                'label' => $adjustment->getLabel(),
                'amount' => $adjustment->getAmount(),
            ];
        };

        $deliveryAdjustments =
            array_map($serializeAdjustment, $order->getAdjustments(AdjustmentInterface::DELIVERY_ADJUSTMENT)->toArray());
        $deliveryPromotionAdjustments =
            array_map($serializeAdjustment, $order->getAdjustments(AdjustmentInterface::DELIVERY_PROMOTION_ADJUSTMENT)->toArray());
        $orderPromotionAdjustments =
            array_map($serializeAdjustment, $order->getAdjustments(AdjustmentInterface::ORDER_PROMOTION_ADJUSTMENT)->toArray());
        $reusablePackagingAdjustments =
            array_map($serializeAdjustment, $order->getAdjustments(AdjustmentInterface::REUSABLE_PACKAGING_ADJUSTMENT)->toArray());
        $taxAdjustments =
            array_map($serializeAdjustment, $order->getAdjustments(AdjustmentInterface::TAX_ADJUSTMENT)->toArray());

        return [
            AdjustmentInterface::DELIVERY_ADJUSTMENT => array_values($deliveryAdjustments),
            AdjustmentInterface::DELIVERY_PROMOTION_ADJUSTMENT => array_values($deliveryPromotionAdjustments),
            AdjustmentInterface::ORDER_PROMOTION_ADJUSTMENT => array_values($orderPromotionAdjustments),
            AdjustmentInterface::REUSABLE_PACKAGING_ADJUSTMENT => array_values($reusablePackagingAdjustments),
            AdjustmentInterface::TAX_ADJUSTMENT => array_values($taxAdjustments),
        ];
    }

    public function normalize($object, $format = null, array $context = array())
    {
        // TODO Document why we use ObjectNormalizer for unsaved orders (?)
        if (null === $object->getId() && !in_array('cart', $context['groups'])) {
            $data = $this->objectNormalizer->normalize($object, $format, $context);
        } else {
            $data = $this->normalizer->normalize($object, $format, $context);
        }

        $data['adjustments'] = $this->normalizeAdjustments($object);

        $restaurant = $object->getRestaurant();

        // Suggest the customer to use reusable packaging via order payload
        if (null !== $restaurant &&
            $restaurant->isDepositRefundEnabled() && $restaurant->isDepositRefundOptin() &&
            $object->isEligibleToReusablePackaging()) {

            $transKey = 'form.checkout_address.reusable_packaging_enabled.label';
            $packagingAmount = $object->getReusablePackagingAmount();

            if ($packagingAmount > 0) {
                $packagingPrice = sprintf('+ %s', $this->priceFormatter->formatWithSymbol($packagingAmount));
            } else {
                $packagingPrice = $this->translator->trans('basics.free');
            }

            // @see https://schema.org/docs/actions.html
            $enableReusablePackagingAction = [
                "@context" => "http://schema.org",
                "@type" => "EnableReusablePackagingAction",
                "actionStatus" => "PotentialActionStatus",
                "description" => $this->translator->trans($transKey, [ '%price%' => $packagingPrice ])
            ];

            $data['potentialAction'] = [ $enableReusablePackagingAction ];
        }

        if (isset($context['is_web']) && $context['is_web']) {

            // Make sure the array is zero-indexed
            $data['items'] = array_values($data['items']);

            $restaurant = $object->getRestaurant();
            if (null === $restaurant) {
                $data['restaurant'] = null;
            } else {

                $fulfillmentMethods = [];
                foreach ($restaurant->getFulfillmentMethods() as $fulfillmentMethod) {
                    if ($fulfillmentMethod->isEnabled()) {
                        $fulfillmentMethods[] = $fulfillmentMethod->getType();
                    }
                }

                $data['restaurant'] = [
                    'id' => $restaurant->getId(),
                    'variableCustomerAmountEnabled' =>
                        $restaurant->getContract() !== null ? $restaurant->getContract()->isVariableCustomerAmountEnabled() : false,
                    'address' => [
                        'latlng' => [
                            $restaurant->getAddress()->getGeo()->getLatitude(),
                            $restaurant->getAddress()->getGeo()->getLongitude(),
                        ]
                    ],
                    'fulfillmentMethods' => $fulfillmentMethods,
                ];
            }

            $data['takeaway'] = $object->isTakeaway();
        }

        return $data;
    }

    public function supportsNormalization($data, $format = null)
    {
        return $this->normalizer->supportsNormalization($data, $format) && $data instanceof Order;
    }

    public function denormalize($data, $class, $format = null, array $context = array())
    {
        $order = $this->normalizer->denormalize($data, $class, $format, $context);

        $order->setChannel($this->channelContext->getChannel());

        if ($order->getState() === Order::STATE_CART && isset($data['promotionCoupon'])) {
            if ($promotionCoupon = $this->promotionCouponRepository->findOneByCode($data['promotionCoupon'])) {
                $order->setPromotionCoupon($promotionCoupon);
                $this->orderProcessor->process($order);
            }
        }

        if (isset($data['reusablePackagingEnabled'])) {
            $this->orderProcessor->process($order);
        }

        if (isset($data['items'])) {
            $orderItems = array_map(function ($item) {

                if (!isset($item['product'])) {
                    return null;
                }

                if (is_array($item['product']) && isset($item['product']['@id'])) {
                    $product = $this->iriConverter->getItemFromIri($item['product']['@id']);
                } else {
                    $product = $this->productRepository->findOneByCode($item['product']);
                }

                if (!$product->hasOptions()) {
                    $productVariant = $this->variantResolver->getVariant($product);
                } else {

                    if (!$product->hasNonAdditionalOptions() && (!isset($item['options']) || empty($item['options']))) {
                        $productVariant = $this->variantResolver->getVariant($product);
                    } else {
                        $optionValues = new \SplObjectStorage();
                        foreach ($item['options'] as $option) {
                            // Legacy
                            if (is_string($option)) {
                                $optionValue = $this->productOptionValueRepository->findOneByCode($option);
                                $optionValues->attach($optionValue);
                            } else {
                                $optionValue = $this->productOptionValueRepository->findOneByCode($option['code']);
                                if ($optionValue && $product->hasOptionValue($optionValue)) {
                                    $quantity = isset($option['quantity']) ? (int) $option['quantity'] : 0;
                                    if (!$optionValue->getOption()->isAdditional() || null === $optionValue->getOption()->getValuesRange()) {
                                        $quantity = 1;
                                    }
                                    if ($quantity > 0) {
                                        $optionValues->attach($optionValue, $quantity);
                                    }
                                }
                            }
                        }
                        $productVariant = $this->variantResolver->getVariantForOptionValues($product, $optionValues);
                    }
                }

                $orderItem = $this->orderItemFactory->createNew();
                $orderItem->setVariant($productVariant);
                $orderItem->setUnitPrice($productVariant->getPrice());

                $this->orderItemQuantityModifier->modify($orderItem, $item['quantity']);

                return $orderItem;

            }, $data['items']);

            $order->clearItems();
            foreach ($orderItems as $orderItem) {
                $this->orderModifier->addToOrder($order, $orderItem);
            }
        }

        // Legacy
        if (isset($data['shippedAt'])) {
            $shippingTimeRange = DateUtils::dateTimeToTsRange(new \DateTime($data['shippedAt']));
            $order->setShippingTimeRange($shippingTimeRange);
        }

        return $order;
    }

    public function supportsDenormalization($data, $type, $format = null)
    {
        return $this->normalizer->supportsDenormalization($data, $type, $format) && $type === Order::class;
    }
}
