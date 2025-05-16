<?php

namespace AppBundle\Api\State;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProcessorInterface;
use AppBundle\Api\Dto\DeliveryInput;
use AppBundle\Entity\Delivery;
use AppBundle\Entity\DeliveryQuote;
use AppBundle\Security\TokenStoreExtractor;
use AppBundle\Service\DeliveryManager;
use Sylius\Component\Currency\Context\CurrencyContextInterface;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;
use Symfony\Component\HttpFoundation\RequestStack;

class DeliveryPersistProcessor implements ProcessorInterface
{
    public function __construct(
        private readonly DeliveryProcessor $decorated,
        private readonly ProcessorInterface $persistProcessor)
    {}

    /**
     * @param DeliveryInput $data
     */
    public function process($data, Operation $operation, array $uriVariables = [], array $context = [])
    {
        /** @var Delivery */
        $delivery = $this->decorated->process($data, $operation, $uriVariables, $context);

        return $this->persistProcessor->process($delivery, $operation, $uriVariables, $context);
    }
}

