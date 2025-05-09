<?php

namespace AppBundle\Api\State;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProcessorInterface;
use ApiPlatform\Symfony\Validator\Exception\ValidationException;
use AppBundle\Api\Dto\DeliveryInput;
use AppBundle\Entity\Delivery;
use AppBundle\Entity\DeliveryQuote;
use AppBundle\Entity\Sylius\ArbitraryPrice;
use AppBundle\Entity\Sylius\UseArbitraryPrice;
use AppBundle\Entity\Sylius\UsePricingRules;
use AppBundle\Pricing\PricingManager;
use AppBundle\Security\TokenStoreExtractor;
use AppBundle\Service\DeliveryManager;
use Sylius\Component\Currency\Context\CurrencyContextInterface;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\Security\Core\Authorization\AuthorizationCheckerInterface;
use Symfony\Component\Validator\Validator\ValidatorInterface;

class DeliveryCreateProcessor implements ProcessorInterface
{
    public function __construct(
        private readonly DeliveryProcessor $decorated,
        private readonly ProcessorInterface $persistProcessor,
        private readonly PricingManager $pricingManager,
        private readonly ValidatorInterface $validator,
        private readonly AuthorizationCheckerInterface $authorizationCheckerInterface)
    {}

    /**
     * @param DeliveryInput $data
     */
    public function process($data, Operation $operation, array $uriVariables = [], array $context = [])
    {
        /** @var Delivery */
        $delivery = $this->decorated->process($data, $operation, $uriVariables, $context);

        // The default API platform validator is called on the object returned by the Controller/Action
        // but we need to validate the delivery before we can create the order
        // @see ApiPlatform\Symfony\EventListener\ValidateListener
        $errors = $this->validator->validate($delivery);
        if (count($errors) > 0) {
            throw new ValidationException($errors);
        }

        $useArbitraryPrice = $this->authorizationCheckerInterface->isGranted('ROLE_DISPATCHER') && $delivery->hasArbitraryPrice();

        if ($useArbitraryPrice) {
            $arbitraryPrice = new ArbitraryPrice(
                $delivery->getArbitraryPrice()->getVariantName(),
                $delivery->getArbitraryPrice()->getValue()
            );
            $this->pricingManager->createOrder(
                $delivery,
                ['pricingStrategy' => new UseArbitraryPrice($arbitraryPrice)]
            );
        } else {
            $priceForOrder = new UsePricingRules();
            $this->pricingManager->createOrder($delivery, [
                'pricingStrategy' => $priceForOrder,

            ]);
        }

        return $this->persistProcessor->process($delivery, $operation, $uriVariables, $context);
    }
}
