<?php

namespace AppBundle\Action\Delivery;

use AppBundle\Entity\Delivery;
use AppBundle\Entity\DeliveryQuote;
use AppBundle\Pricing\PricingManager;
use AppBundle\Security\TokenStoreExtractor;
use Sylius\Component\Currency\Context\CurrencyContextInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;

class Quote
{
    public function __construct(
        private readonly TokenStoreExtractor $storeExtractor,
        private readonly PricingManager $pricingManager,
        private readonly CurrencyContextInterface $currencyContext)
    {
    }

    public function __invoke(Delivery $data, Request $request)
    {
        $store = $this->storeExtractor->extractStore();
        $amount = $this->pricingManager->getPrice($data, $store->getPricingRuleSet());

        if (null === $amount) {
            throw new BadRequestHttpException('Price could not be calculated');
        }

        $content = $request->getContent();

        $quote = new DeliveryQuote();
        $quote->setStore($store);
        $quote->setState(DeliveryQuote::STATE_NEW);
        $quote->setAmount($amount);
        $quote->setCurrencyCode($this->currencyContext->getCurrencyCode());
        $quote->setPayload(json_encode(json_decode($content)));
        $quote->setExpiresAt(new \DateTime('+1 day'));

        return $quote;
    }
}
