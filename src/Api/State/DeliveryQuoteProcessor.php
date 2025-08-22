<?php

namespace AppBundle\Api\State;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProcessorInterface;
use AppBundle\Api\Dto\DeliveryInputDto;
use AppBundle\Entity\Delivery;
use AppBundle\Entity\DeliveryQuote;
use AppBundle\Pricing\PricingManager;
use AppBundle\Security\TokenStoreExtractor;
use Sylius\Component\Currency\Context\CurrencyContextInterface;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;
use Symfony\Component\HttpFoundation\RequestStack;

class DeliveryQuoteProcessor implements ProcessorInterface
{
    public function __construct(
        private readonly DeliveryProcessor $decorated,
        private readonly ProcessorInterface $persistProcessor,
        private readonly TokenStoreExtractor $storeExtractor,
        private readonly PricingManager $pricingManager,
        private readonly CurrencyContextInterface $currencyContext,
        private readonly RequestStack $requestStack)
    {}

    /**
     * @param DeliveryInputDto $data
     */
    public function process($data, Operation $operation, array $uriVariables = [], array $context = [])
    {
        /** @var Delivery */
        $data = $this->decorated->process($data, $operation, $uriVariables, $context);

        $store = $this->storeExtractor->extractStore();
        $amount = $this->pricingManager->getPrice($data, $store->getPricingRuleSet());

        if (null === $amount) {
            throw new BadRequestHttpException('Price could not be calculated');
        }

        $request = $this->requestStack->getCurrentRequest();

        $quote = new DeliveryQuote();
        $quote->setStore($store);
        $quote->setState(DeliveryQuote::STATE_NEW);
        $quote->setAmount($amount);
        $quote->setCurrencyCode($this->currencyContext->getCurrencyCode());
        $quote->setPayload(json_encode($request->toArray()));
        $quote->setExpiresAt(new \DateTime('+1 day'));

        return $this->persistProcessor->process($quote, $operation, $uriVariables, $context);
    }
}
