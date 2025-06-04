<?php

namespace AppBundle\Api\State;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProcessorInterface;
use ApiPlatform\Symfony\Validator\Exception\ValidationException;
use AppBundle\Api\Dto\DeliveryMapper;
use AppBundle\Api\Dto\DeliveryFromTasksInput;
use AppBundle\Api\Dto\DeliveryDto;
use AppBundle\Entity\Delivery;
use AppBundle\Entity\Sylius\ArbitraryPrice;
use AppBundle\Entity\Sylius\UseArbitraryPrice;
use AppBundle\Entity\Sylius\UsePricingRules;
use AppBundle\Pricing\PricingManager;
use AppBundle\Service\OrderManager;
use AppBundle\Sylius\Order\OrderFactory;
use AppBundle\Sylius\Order\OrderInterface;
use Psr\Log\LoggerInterface;
use Recurr\Exception\InvalidRRule;
use Recurr\Rule;
use Symfony\Component\Security\Core\Authorization\AuthorizationCheckerInterface;
use Symfony\Component\Validator\Validator\ValidatorInterface;

class DeliveryCreateOrUpdateProcessor implements ProcessorInterface
{
    public function __construct(
        private readonly DeliveryProcessor $decorated,
        private readonly ProcessorInterface $persistProcessor,
        private readonly PricingManager $pricingManager,
        private readonly OrderFactory $orderFactory,
        private readonly OrderManager $orderManager,
        private readonly DeliveryMapper $deliveryMapper,
        private readonly AuthorizationCheckerInterface $authorizationCheckerInterface,
        private readonly ValidatorInterface $validator,
        private readonly LoggerInterface $logger,
    )
    {}

    /**
     * @param DeliveryDto|DeliveryFromTasksInput $data
     */
    public function process($data, Operation $operation, array $uriVariables = [], array $context = []): DeliveryDto
    {
        /** @var Delivery $delivery */
        $delivery = $this->decorated->process($data, $operation, $uriVariables, $context);

        // The default API platform validator is called on the object returned by the Controller/Action
        // but we need to validate the delivery before we can create the order
        // @see ApiPlatform\Symfony\EventListener\ValidateListener
        $errors = $this->validator->validate($delivery);
        if (count($errors) > 0) {
            throw new ValidationException($errors);
        }

        /** @var ArbitraryPrice|null $arbitraryPrice */
        $arbitraryPrice = null;
        if ($this->authorizationCheckerInterface->isGranted('ROLE_DISPATCHER') && $data instanceof DeliveryDto && $data->order?->arbitraryPrice) {
            $arbitraryPrice = new ArbitraryPrice(
                $data->order->arbitraryPrice->variantName,
                $data->order->arbitraryPrice->variantPrice
            );
        }

        $pricingStrategy = new UsePricingRules();

        if (!is_null($arbitraryPrice)) {
            $pricingStrategy = new UseArbitraryPrice($arbitraryPrice);
        }

        $isCreateOrderMode = is_null($delivery->getId());

        /** @var OrderInterface $order */
        $order = null;
        if ($isCreateOrderMode) {
            // New delivery/order

            $order = $this->pricingManager->createOrder(
                $delivery,
                [
                    'pricingStrategy' => $pricingStrategy,
                ]
            );

            //FIXME: update behat tests and re-enable
            // automatically accept order created by dispatchers
//            if ($this->authorizationCheckerInterface->isGranted('ROLE_DISPATCHER')) {
//                $order->setState(OrderInterface::STATE_ACCEPTED);
//            }

        } else {
            // Existing delivery/order

            $order = $delivery->getOrder();

            if (!is_null($arbitraryPrice)) {
                if (is_null($order)) {
                    // Should not happen normally, but just in case
                    // there is still some delivery created without an order
                    $order = $this->pricingManager->createOrder(
                        $delivery,
                        [
                            'pricingStrategy' => new UseArbitraryPrice($arbitraryPrice),
                        ]
                    );
                } else {
                    $this->orderFactory->updateDeliveryPrice($order, $delivery, $arbitraryPrice);
                }
            }
        }

        /** @var string $rrule */
        $rrule = null;
        if ($this->authorizationCheckerInterface->isGranted('ROLE_DISPATCHER') && $data instanceof DeliveryDto && $isCreateOrderMode) {
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
                    $pricingStrategy
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
        if ($this->authorizationCheckerInterface->isGranted('ROLE_DISPATCHER') && $data instanceof DeliveryDto && !is_null($data->order?->isSavedOrder)) {
            $isSavedOrder = $data->order->isSavedOrder;
            $this->orderManager->setBookmark($order, $isSavedOrder);
        }

        $this->persistProcessor->process($delivery, $operation, $uriVariables, $context);

        return $this->deliveryMapper->map(
            $delivery,
            $order,
            $arbitraryPrice,
            $isSavedOrder,
        );
    }
}
