<?php

namespace AppBundle\Serializer\JsonLd;

use ApiPlatform\Core\Api\IriConverterInterface;
use ApiPlatform\Core\JsonLd\Serializer\ItemNormalizer;
use AppBundle\Entity\Sylius\Order;
use AppBundle\LoopEat\Client as LoopeatClient;
use AppBundle\LoopEat\Context as LoopeatContext;
use AppBundle\LoopEat\ContextInitializer as LoopeatContextInitializer;
use AppBundle\Sylius\Order\AdjustmentInterface;
use AppBundle\Sylius\Product\LazyProductVariantResolverInterface;
use AppBundle\Utils\DateUtils;
use AppBundle\Utils\OptionsPayloadConverter;
use AppBundle\Utils\PriceFormatter;
use Sylius\Component\Channel\Context\ChannelContextInterface;
use Sylius\Bundle\PromotionBundle\Doctrine\ORM\PromotionCouponRepository;
use Sylius\Component\Order\Model\AdjustmentInterface as BaseAdjustmentInterface;
use Sylius\Component\Order\Modifier\OrderItemQuantityModifierInterface;
use Sylius\Component\Order\Modifier\OrderModifierInterface;
use Sylius\Component\Order\Processor\OrderProcessorInterface;
use Sylius\Component\Product\Repository\ProductRepositoryInterface;
use Sylius\Component\Resource\Factory\FactoryInterface;
use Symfony\Contracts\Translation\TranslatorInterface;
use Symfony\Component\Serializer\Normalizer\DenormalizerInterface;
use Symfony\Component\Serializer\Normalizer\NormalizerInterface;
use Symfony\Component\Serializer\Normalizer\ObjectNormalizer;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;

class OrderNormalizer implements NormalizerInterface, DenormalizerInterface
{
    private $normalizer;
    private $channelContext;
    private $productRepository;
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
        OptionsPayloadConverter $optionsPayloadConverter,
        LazyProductVariantResolverInterface $variantResolver,
        FactoryInterface $orderItemFactory,
        OrderItemQuantityModifierInterface $orderItemQuantityModifier,
        OrderModifierInterface $orderModifier,
        IriConverterInterface $iriConverter,
        PromotionCouponRepository $promotionCouponRepository,
        OrderProcessorInterface $orderProcessor,
        PriceFormatter $priceFormatter,
        TranslatorInterface $translator,
        UrlGeneratorInterface $urlGenerator,
        LoopeatClient $loopeatClient,
        LoopeatContextInitializer $loopeatContextInitializer)
    {
        $this->normalizer = $normalizer;
        $this->channelContext = $channelContext;
        $this->productRepository = $productRepository;
        $this->optionsPayloadConverter = $optionsPayloadConverter;
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
        $this->urlGenerator = $urlGenerator;
        $this->loopeatClient = $loopeatClient;
        $this->loopeatContextInitializer = $loopeatContextInitializer;
    }

    public function normalize($object, $format = null, array $context = array())
    {
        // TODO Document why we use ObjectNormalizer for unsaved orders (?)
        if (null === $object->getId() && !in_array('cart', $context['groups'])) {
            $data = $this->objectNormalizer->normalize($object, $format, $context);
        } else {

            // FIXME
            //
            // This is here to avoid errors like
            // > Unable to generate an IRI for "AppBundle\Entity\Address"
            //
            // 1/ The customer has a cart at restaurant A, with shippingAddress = null
            // 2/ The customer goes to restaurant B, and changes the address
            // 3/ The cart is not persisted, and shippingAddress.id = NULL

            $fixShippingAddress = false;

            $shippingAddress = $object->getShippingAddress();
            if (null !== $shippingAddress && null === $shippingAddress->getId()) {
                $object->setShippingAddress(null);
                $shippingAddressData = $this->objectNormalizer->normalize($shippingAddress, $format, $context);
                $fixShippingAddress = true;
            }

            $data = $this->normalizer->normalize($object, $format, $context);

            if ($fixShippingAddress) {
                $object->setShippingAddress($shippingAddress);
                $data['shippingAddress'] = $shippingAddressData;
            }
        }

        $restaurant = $object->getRestaurant();

        // Suggest the customer to use reusable packaging via order payload
        if (null !== $restaurant &&
            $object->isEligibleToReusablePackaging() &&
            $restaurant->isDepositRefundOptin() &&
            // We don't allow (yet) to toggle reusable packaging for Dabba
            // https://github.com/coopcycle/coopcycle-app/issues/1503
            !$restaurant->isDabbaEnabled()) {

            $transKey = 'form.checkout_address.reusable_packaging_enabled.label';
            $packagingAmount = $object->getReusablePackagingAmount();

            $packagingPrice = '';
            if ($restaurant->isLoopeatEnabled()) {
                $transKey = 'form.checkout_address.reusable_packaging_loopeat_enabled.label';
            } elseif ($packagingAmount > 0) {
                $packagingPrice = sprintf('+ %s', $this->priceFormatter->formatWithSymbol($packagingAmount));
            } else {
                $packagingPrice = $this->translator->trans('basics.free');
            }

            // @see https://schema.org/docs/actions.html
            $enableReusablePackagingAction = [
                "@context" => "http://schema.org",
                "@type" => "EnableReusablePackagingAction",
                "actionStatus" => "PotentialActionStatus",
                "description" => $this->translator->trans($transKey, [ '%price%' => $packagingPrice ]),
            ];

            if ($restaurant->isLoopeatEnabled()) {
                $enableReusablePackagingAction['loopeatOAuthUrl'] = $this->loopeatClient->getOAuthAuthorizeUrl([
                    'state' => $this->loopeatClient->createStateParamForOrder($object, $useDeepLink = true),
                ]);
            }

            $data['potentialAction'] = [ $enableReusablePackagingAction ];
        }

        if (null !== $restaurant && $restaurant->isLoopeatEnabled()) {
            $data['loopeatContext'] = $this->loopeatContextInitializer->initialize($object);
        }

        if (isset($context['is_web']) && $context['is_web']) {

            // Make sure the array is zero-indexed
            $data['items'] = array_values($data['items']);

            if (!$object->hasVendor()) {
                $data['vendor'] = null;
            } else {

                $vendor = $object->getVendor();

                $fulfillmentMethods = [];
                foreach ($vendor->getFulfillmentMethods() as $fulfillmentMethod) {
                    if ($fulfillmentMethod->isEnabled()) {
                        $fulfillmentMethods[] = $fulfillmentMethod->getType();
                    }
                }

                $data['vendor'] = [
                    'id' => $vendor->getId(),
                    'variableCustomerAmountEnabled' =>
                        $vendor->getContract() !== null ? $vendor->getContract()->isVariableCustomerAmountEnabled() : false,
                    'address' => [
                        'latlng' => [
                            $vendor->getAddress()->getGeo()->getLatitude(),
                            $vendor->getAddress()->getGeo()->getLongitude(),
                        ]
                    ],
                    'fulfillmentMethods' => $fulfillmentMethods,
                ];
            }

            $data['takeaway'] = $object->isTakeaway();
        }

        $data['invitation'] = null;

        if (null !== ($invitation = $object->getInvitation())) {
            $data['invitation'] = $invitation->getSlug();
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
                        $optionValues = $this->optionsPayloadConverter->convert($product, $item['options']);
                        $productVariant = $this->variantResolver->getVariantForOptionValues($product, $optionValues);
                    }
                }

                $orderItem = $this->orderItemFactory->createNew();
                $orderItem->setVariant($productVariant);
                $orderItem->setUnitPrice($productVariant->getPrice());

                $this->orderItemQuantityModifier->modify($orderItem, $item['quantity']);

                return $orderItem;

            }, $data['items']);

            $orderItems = array_filter($orderItems);

            if (count($orderItems) > 0) {
                $order->clearItems();
                foreach ($orderItems as $orderItem) {
                    $this->orderModifier->addToOrder($order, $orderItem);
                }
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
