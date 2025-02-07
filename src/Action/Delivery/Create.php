<?php

namespace AppBundle\Action\Delivery;

use ApiPlatform\Core\Bridge\Symfony\Validator\Exception\ValidationException;
use AppBundle\Api\Exception\BadRequestHttpException;
use AppBundle\Entity\Delivery;
use AppBundle\Entity\Sylius\ArbitraryPrice;
use AppBundle\Entity\Sylius\UseArbitraryPrice;
use AppBundle\Entity\Sylius\UsePricingRules;
use AppBundle\Exception\Pricing\NoRuleMatchedException;
use AppBundle\Pricing\PricingManager;
use Symfony\Component\Security\Core\Authorization\AuthorizationCheckerInterface;
use Symfony\Component\Validator\Validator\ValidatorInterface;
use Symfony\Contracts\Translation\TranslatorInterface;

class Create
{
    public function __construct(
        private readonly PricingManager $pricingManager,
        private readonly ValidatorInterface $validator,
        private readonly AuthorizationCheckerInterface $authorizationCheckerInterface,
        private readonly TranslatorInterface $translator
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

        $useArbitraryPrice = $this->authorizationCheckerInterface->isGranted('ROLE_ADMIN') && $data->hasArbitraryPrice();

        if ($useArbitraryPrice) {
            $arbitraryPrice = new ArbitraryPrice(
                $data->getArbitraryPrice()->getVariantName(),
                $data->getArbitraryPrice()->getValue()
            );
            $this->pricingManager->createOrder(
                $data,
                ['pricingStrategy' => new UseArbitraryPrice($arbitraryPrice)]
            );
        } else {
            $priceForOrder = new UsePricingRules();
            try {
                $this->pricingManager->createOrder($data, [
                    'pricingStrategy' => $priceForOrder,
                    // Force an admin to fix the pricing rules
                    // maybe it would be a better UX to create an incident instead
                    'throwException' => $this->authorizationCheckerInterface->isGranted('ROLE_ADMIN')
                ]);
    
            } catch (NoRuleMatchedException $e) {
                $message = $this->translator->trans('delivery.price.error.priceCalculation', [], 'validators');
                throw new BadRequestHttpException($message);
            }
        }

        return $data;
    }
}
