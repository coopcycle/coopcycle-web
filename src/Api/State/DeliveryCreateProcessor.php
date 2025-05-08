<?php

namespace AppBundle\Api\State;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProcessorInterface;
use ApiPlatform\Symfony\Validator\Exception\ValidationException;
use AppBundle\Api\Dto\DeliveryFromTasksInput;
use AppBundle\Api\Dto\DeliveryInput;
use AppBundle\Entity\Delivery;
use AppBundle\Entity\Sylius\ArbitraryPrice;
use AppBundle\Entity\Sylius\UseArbitraryPrice;
use AppBundle\Entity\Sylius\UsePricingRules;
use AppBundle\Pricing\PricingManager;
use AppBundle\Sylius\Order\OrderFactory;
use Symfony\Component\Security\Core\Authorization\AuthorizationCheckerInterface;
use Symfony\Component\Validator\Validator\ValidatorInterface;

class DeliveryCreateProcessor implements ProcessorInterface
{
    public function __construct(
        private readonly DeliveryProcessor $decorated,
        private readonly ProcessorInterface $persistProcessor,
        private readonly PricingManager $pricingManager,
        private readonly OrderFactory $orderFactory,
        private readonly ValidatorInterface $validator,
        private readonly AuthorizationCheckerInterface $authorizationCheckerInterface)
    {}

    /**
     * @param DeliveryInput|DeliveryFromTasksInput $data
     */
    public function process($data, Operation $operation, array $uriVariables = [], array $context = [])
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

        /** @var ArbitraryPrice $arbitraryPrice */
        $arbitraryPrice = null;
        if ($data instanceof DeliveryInput) {
            $arbitraryPrice = $data->arbitraryPrice;
        }

        $useArbitraryPrice = $this->authorizationCheckerInterface->isGranted('ROLE_DISPATCHER') && $arbitraryPrice;

        if (null === $delivery->getId()) {
            // New delivery

            if ($useArbitraryPrice) {
                $this->pricingManager->createOrder(
                    $delivery,
                    [
                        'pricingStrategy' => new UseArbitraryPrice($arbitraryPrice)
                    ]
                );
            } else {
                $priceForOrder = new UsePricingRules();
                $this->pricingManager->createOrder(
                    $delivery,
                    [
                        'pricingStrategy' => $priceForOrder,
                    ]
                );
            }

        } else {
            // Existing delivery

            if ($useArbitraryPrice) {
                $order = $delivery->getOrder();
                if (null === $order) {
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

        return $this->persistProcessor->process($delivery, $operation, $uriVariables, $context);
    }
}
