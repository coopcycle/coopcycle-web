<?php

namespace AppBundle\Api\State;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProcessorInterface;
use ApiPlatform\Symfony\Validator\Exception\ValidationException;
use AppBundle\Api\Dto\DeliveryFromTasksInput;
use AppBundle\Api\Dto\DeliveryInputDto;
use AppBundle\Entity\Delivery;
use AppBundle\Entity\Sylius\ArbitraryPrice;
use AppBundle\Entity\Sylius\UpdateManualSupplements;
use AppBundle\Entity\Sylius\UseArbitraryPrice;
use AppBundle\Entity\Sylius\CalculateUsingPricingRules;
use AppBundle\Pricing\ManualSupplement;
use AppBundle\Pricing\ManualSupplements;
use AppBundle\Pricing\PricingManager;
use AppBundle\Service\DeliveryOrderManager;
use AppBundle\Service\OrderManager;
use AppBundle\Sylius\Order\OrderInterface;
use AppBundle\Sylius\Product\ProductOptionValueInterface;
use Doctrine\ORM\EntityNotFoundException;
use Psr\Log\LoggerInterface;
use Recurr\Exception\InvalidRRule;
use Recurr\Rule;
use Symfony\Component\Security\Core\Authorization\AuthorizationCheckerInterface;
use Symfony\Component\Validator\Validator\ValidatorInterface;

class DeliveryCreateOrUpdateProcessor implements ProcessorInterface
{
    public function __construct(
        private readonly DeliveryProcessor $decorated,
        private readonly ManualSupplementsProcessor $manualSupplementsProcessor,
        private readonly ProcessorInterface $persistProcessor,
        private readonly PricingManager $pricingManager,
        private readonly DeliveryOrderManager $deliveryOrderManager,
        private readonly OrderManager $orderManager,
        private readonly AuthorizationCheckerInterface $authorizationCheckerInterface,
        private readonly ValidatorInterface $validator,
        private readonly LoggerInterface $logger,
    ) {
    }

    /**
     * @param DeliveryInputDto|DeliveryFromTasksInput $data
     */
    public function process(
        $data,
        Operation $operation,
        array $uriVariables = [],
        array $context = []
    ): Delivery {
        /** @var Delivery $delivery */
        $delivery = $this->decorated->process($data, $operation, $uriVariables, $context);

        // The default API platform validator is called on the object returned by the Controller/Action
        // but we need to validate the delivery before we can create the order
        // @see ApiPlatform\Symfony\EventListener\ValidateListener
        $errors = $this->validator->validate($delivery);
        if (count($errors) > 0) {
            throw new ValidationException($errors);
        }

        // Extract manual supplements from the DTO
        /** @var ManualSupplements|null $manualSupplements */
        $manualSupplements = null;
        if ($this->authorizationCheckerInterface->isGranted(
                'ROLE_DISPATCHER'
            ) && $data instanceof DeliveryInputDto) {
            $manualSupplements = $this->manualSupplementsProcessor->process(
                $data,
                $operation,
                $uriVariables,
                $context
            );
        }

        /** @var ArbitraryPrice|null $arbitraryPrice */
        $arbitraryPrice = null;
        if ($this->authorizationCheckerInterface->isGranted(
                'ROLE_DISPATCHER'
            ) && $data instanceof DeliveryInputDto && $data->order?->arbitraryPrice) {
            $arbitraryPrice = new ArbitraryPrice(
                $data->order->arbitraryPrice->variantName,
                $data->order->arbitraryPrice->variantPrice
            );
        }

        $onCreatePricingStrategy = new CalculateUsingPricingRules();

        if (!is_null($arbitraryPrice)) {
            $onCreatePricingStrategy = new UseArbitraryPrice($arbitraryPrice);
        } elseif (!is_null($manualSupplements)) {
            $onCreatePricingStrategy = new CalculateUsingPricingRules($manualSupplements);
        }

        $isCreateOrderMode = is_null($delivery->getId());

        if ($isCreateOrderMode) {
            $store = $delivery->getStore();
            if (!is_null($store)) {
                $store->addDelivery($delivery);
            }
        }

        /** @var OrderInterface $order */
        $order = null;
        if ($isCreateOrderMode) {
            // New delivery/order

            $order = $this->deliveryOrderManager->createOrder(
                $delivery,
                [
                    'pricingStrategy' => $onCreatePricingStrategy,
                ]
            );

            if ($this->authorizationCheckerInterface->isGranted('ROLE_DISPATCHER')) {
                $order->setState(OrderInterface::STATE_ACCEPTED);
            }
        } else {
            // Existing delivery/order

            $order = $delivery->getOrder();

            if ($this->authorizationCheckerInterface->isGranted('ROLE_DISPATCHER')) {
                if (is_null($order)) {
                    // Should not happen normally, but just in case
                    // if there is still some delivery created without an order
                    $order = $this->deliveryOrderManager->createOrder(
                        $delivery,
                        [
                            'pricingStrategy' => $onCreatePricingStrategy,
                        ]
                    );
                }

                if (!is_null($arbitraryPrice)) {
                    $productVariants = $this->pricingManager->getProductVariantsWithPricingStrategy(
                        $delivery,
                        new UseArbitraryPrice($arbitraryPrice)
                    );
                    $this->pricingManager->processDeliveryOrder(
                        $order,
                        $productVariants
                    );
                } elseif ($data instanceof DeliveryInputDto && $data->order?->recalculatePrice) {
                    $productVariants = $this->pricingManager->getProductVariantsWithPricingStrategy(
                        $delivery,
                        new CalculateUsingPricingRules($manualSupplements)
                    );
                    $this->pricingManager->processDeliveryOrder($order, $productVariants);
                } elseif (!is_null($manualSupplements) && $this->hasManualSupplementsChanged($manualSupplements, $order)) {
                    $existingProductVariants = [];
                    foreach ($order->getItems() as $item) {
                        $existingProductVariants[] = $item->getVariant();
                    }

                    $productVariants = $this->pricingManager->getProductVariantsWithPricingStrategy(
                        $delivery,
                        new UpdateManualSupplements($manualSupplements, $existingProductVariants),
                    );

                    $this->pricingManager->processDeliveryOrder($order, $productVariants);
                } else {
                    $this->logger->info('Keeping existing price', ['order' => $order->getId()]);
                }
            }
        }

        /** @var string $rrule */
        $rrule = null;
        if ($this->authorizationCheckerInterface->isGranted(
                'ROLE_DISPATCHER'
            ) && $data instanceof DeliveryInputDto && $isCreateOrderMode) {
            $rrule = $data->rrule;
        }

        // Only when creating a new delivery/order
        if ($rrule) {
            $store = $delivery->getStore();

            $recurrRule = null;
            try {
                $recurrRule = new Rule($data->rrule);
            } catch (InvalidRRule $e) {
                $this->logger->warning('Invalid recurrence rule', [
                    'rule' => $data->rrule,
                    'exception' => $e->getMessage(),
                ]);
            }

            if ($recurrRule) {
                $recurrenceRule = $this->pricingManager->createRecurrenceRule(
                    $store,
                    $delivery,
                    $recurrRule,
                    $onCreatePricingStrategy
                );

                if (!is_null($recurrenceRule)) {
                    $order->setSubscription($recurrenceRule);

                    foreach ($delivery->getTasks() as $task) {
                        $task->setRecurrenceRule($recurrenceRule);
                    }
                }
            }
        }

        $isSavedOrder = false;
        if ($this->authorizationCheckerInterface->isGranted(
                'ROLE_DISPATCHER'
            ) && $data instanceof DeliveryInputDto && !is_null($data->order?->isSavedOrder)) {
            $isSavedOrder = $data->order->isSavedOrder;
            $this->orderManager->setBookmark($order, $isSavedOrder);
        }

        $this->persistProcessor->process($delivery, $operation, $uriVariables, $context);

        return $delivery;
    }

    private function hasManualSupplementsChanged(ManualSupplements $manualSupplements, OrderInterface $existingOrder): bool
    {
        $existingManualSupplements = [];

        foreach ($existingOrder->getItems() as $item) {
            $variant = $item->getVariant();

            /** @var ProductOptionValueInterface $optionValue */
            foreach ($variant->getOptionValues() as $optionValue) {
                try {
                    // Find the PricingRule linked to this ProductOptionValue
                    $pricingRule = $optionValue->getPricingRule();
                } catch (EntityNotFoundException $e) {
                    // This happens when a pricing rule has been modified
                    // and the linked product option value has been disabled
                    // but is still attached to a product variant
                    $pricingRule = null;
                }

                if (!is_null($pricingRule) && $pricingRule->isManualSupplement()) {
                    $existingManualSupplements[] = new ManualSupplement($pricingRule, $variant->formatQuantityForOptionValue($optionValue));
                }
            }
        }

        $added = 0;
        $removed = 0;
        $changed = 0;

        foreach ($manualSupplements->orderSupplements as $supplement) {
            $foundSupplement = null;
            foreach ($existingManualSupplements as $existingSupplement) {
                if ($existingSupplement->pricingRule === $supplement->pricingRule) {
                    $foundSupplement = $existingSupplement;
                    break;
                }
            }

            if (is_null($foundSupplement)) {
                $added++;
            } elseif ($foundSupplement->quantity !== $supplement->quantity) {
                $changed++;
            }
        }

        foreach ($existingManualSupplements as $existingSupplement) {
            $foundSupplement = null;
            foreach ($manualSupplements->orderSupplements as $supplement) {
                if ($existingSupplement->pricingRule === $supplement->pricingRule) {
                    $foundSupplement = $supplement;
                    break;
                }
            }

            if (is_null($foundSupplement)) {
                $removed++;
            }
        }

        if ($added > 0 || $removed > 0 || $changed > 0) {
            return true;
        }

        return false;
    }
}
