<?php

namespace AppBundle\Action\Delivery;

use ApiPlatform\Core\Bridge\Symfony\Validator\Exception\ValidationException;
use AppBundle\Entity\Delivery;
use AppBundle\Entity\Sylius\ArbitraryPrice;
use AppBundle\Entity\Sylius\UseArbitraryPrice;
use AppBundle\Pricing\PricingManager;
use AppBundle\Sylius\Order\OrderFactory;
use Symfony\Component\Security\Core\Authorization\AuthorizationCheckerInterface;
use Symfony\Component\Validator\Validator\ValidatorInterface;

class Edit
{
    public function __construct(
        private readonly PricingManager $pricingManager,
        private readonly ValidatorInterface $validator,
        private readonly AuthorizationCheckerInterface $authorizationCheckerInterface,
        private readonly OrderFactory $orderFactory
    ) {}

    public function __invoke(Delivery $data): mixed
    {
        $errors = $this->validator->validate($data);
        if (count($errors) > 0) {
            throw new ValidationException($errors);
        }

        $order = $data->getOrder();
        $useArbitraryPrice = $this->authorizationCheckerInterface->isGranted('ROLE_ADMIN') && !is_null($data->getDeliveryPriceInput());

        if ($useArbitraryPrice) {
            $arbitraryPrice = new ArbitraryPrice(
                $data->getDeliveryPriceInput()->getVariantName(),
                $data->getDeliveryPriceInput()->getPriceIncVATcents()
            );

            if (null === $order) {
                // Should not happen normally, but just in case
                // there is still some delivery created without an order
                $order = $this->pricingManager->createOrder($data, [
                    'pricingStrategy' => new UseArbitraryPrice($arbitraryPrice),
                ]);
            } else {
                $this->orderFactory->updateDeliveryPrice($order, $data, $arbitraryPrice);
            }
        }

        return $data;
    }
}
