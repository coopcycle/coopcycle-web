<?php

namespace AppBundle\Action\Delivery;

use ApiPlatform\Core\Bridge\Symfony\Validator\Exception\ValidationException;
use AppBundle\Entity\Delivery;
use AppBundle\Entity\Sylius\ArbitraryPrice;
use AppBundle\Entity\Sylius\UseArbitraryPrice;
use AppBundle\Pricing\PricingManager;
use Symfony\Component\Security\Core\Authorization\AuthorizationCheckerInterface;
use Symfony\Component\Validator\Validator\ValidatorInterface;

class Create
{
    public function __construct(
        private readonly PricingManager $pricingManager,
        private readonly ValidatorInterface $validator,
        private readonly AuthorizationCheckerInterface $authorizationCheckerInterface
    ) {}

    public function __invoke(Delivery $data)
    {
        // The default API platform validator is called on the object returned by the Controller/Action
        // but we need to validate the delivery before we can create the order
        // @see ApiPlatform\Core\Validator\EventListener\ValidateListener
        $errors = $this->validator->validate($data);
        if (count($errors) > 0) {
            throw new ValidationException($errors);
        }

        $useArbitraryPrice = $this->authorizationCheckerInterface->isGranted('ROLE_ADMIN') && !is_null($data->getVariantIncVATPrice());

        if ($useArbitraryPrice) {
            $arbitraryPrice = new ArbitraryPrice('variant name', $data->getVariantIncVATPrice());
            $this->pricingManager->createOrder(
                $data,
                ['pricingStrategy' => new UseArbitraryPrice($arbitraryPrice)]
            );
        } else {
            $this->pricingManager->createOrder($data);
        }

        return $data;
    }
}
