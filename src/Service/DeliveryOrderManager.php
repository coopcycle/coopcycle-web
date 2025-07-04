<?php

namespace AppBundle\Service;

use AppBundle\Action\Incident\CreateIncident;
use AppBundle\Action\Utils\TokenStorageTrait;
use AppBundle\Entity\Delivery;
use AppBundle\Entity\Incident\Incident;
use AppBundle\Entity\Sylius\ArbitraryPrice;
use AppBundle\Entity\Sylius\PricingStrategy;
use AppBundle\Entity\Sylius\UseArbitraryPrice;
use AppBundle\Entity\Sylius\UsePricingRules;
use AppBundle\Entity\Task\RecurrenceRule;
use AppBundle\Entity\User;
use AppBundle\Exception\Pricing\NoRuleMatchedException;
use AppBundle\Pricing\PricingManager;
use AppBundle\Sylius\Order\OrderFactory;
use AppBundle\Sylius\Order\OrderInterface;
use AppBundle\Sylius\Product\ProductVariantInterface;
use Doctrine\ORM\EntityManagerInterface;
use Psr\Log\LoggerInterface;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\Security\Core\Authentication\Token\Storage\TokenStorageInterface;
use Symfony\Contracts\Translation\TranslatorInterface;

class DeliveryOrderManager
{
    use TokenStorageTrait;

    public function __construct(
        TokenStorageInterface $tokenStorage,
        private readonly EntityManagerInterface $entityManager,
        private readonly RequestStack $requestStack,
        private readonly TranslatorInterface $translator,
        private readonly DeliveryManager $deliveryManager,
        private readonly OrderManager $orderManager,
        private readonly OrderFactory $orderFactory,
        private readonly PricingManager $pricingManager,
        private readonly CreateIncident $createIncident,
        private readonly LoggerInterface $logger,
    ) {
        $this->tokenStorage = $tokenStorage;
    }

    /**
     * @throws NoRuleMatchedException
     */
    public function createOrder(Delivery $delivery, array $optionalArgs = []): OrderInterface
    {
        // Defining a default value in the method signature fails in the phpunit tests
        // even though it seems that it was fixed: https://github.com/sebastianbergmann/phpunit/commit/658d8decbec90c4165c0b911cf6cfeb5f6601cae
        $defaults = [
            'pricingStrategy' => new UsePricingRules(),
            'persist' => true,
            // If set to true, an exception will be thrown when a price cannot be calculated
            // If set to false, a price of 0 will be set and an incident will be created
            'throwException' => false,
        ];
        $optionalArgs += $defaults;

        $pricingStrategy = $optionalArgs['pricingStrategy'];
        $persist = $optionalArgs['persist'];
        $throwException = $optionalArgs['throwException'];

        if (null === $pricingStrategy) {
            $pricingStrategy = new UsePricingRules();
        }

        $productVariants = $this->pricingManager->getPriceWithPricingStrategy(
            $delivery,
            $pricingStrategy
        );
        $incident = null;

        if (count($productVariants) === 0) {
            if ($throwException) {
                throw new NoRuleMatchedException();
            }

            // otherwise; set price to 0 and create an incident
            $productVariants = [
                $this->pricingManager->getCustomProductVariant(
                    $delivery,
                    new ArbitraryPrice($this->translator->trans('form.delivery.price.missing'), 0)
                ),
            ];
            $incident = new Incident();
        }

        $order = $this->orderFactory->createForDelivery($delivery);
        $this->pricingManager->processDeliveryOrder($order, $productVariants);

        if ($persist) {
            // We need to persist the order first,
            // because an auto increment is needed to generate a number
            $this->entityManager->persist($order);
            $this->entityManager->flush();

            $this->orderManager->onDemand($order);

            $this->entityManager->flush();

            $user = $this->getUser();

            $isUserWithAccount = $user instanceof User && null !== $user->getId();
            // If it's not a user with an account, it could be an ApiApp
            // ApiKey: see BearerTokenAuthenticator
            // OAuth client: League\Bundle\OAuth2ServerBundle\Security\User\NullUser

            if (null !== $incident) {
                $title = $this->translator->trans('form.delivery.price.missing.incident', [
                    '%number%' => $order->getNumber(),
                ]);

                //FIXME: allow to set $createdBy API clients (ApiApp) and integrations; see Incident::createdBy
                if (!$isUserWithAccount) {
                    $title = $title.' (API client)';
                }

                $incident->setTitle($title);
                $incident->setFailureReasonCode('PRICE_REVIEW_NEEDED');
                $incident->setTask($delivery->getPickup());

                $this->createIncident->__invoke(
                    $incident,
                    $isUserWithAccount ? $user : null,
                    $this->requestStack->getCurrentRequest()
                );
            }
        }

        return $order;
    }

    public function createOrderFromRecurrenceRule(
        RecurrenceRule $recurrenceRule,
        string $startDate,
        bool $persist = true,
        bool $throwException = false
    ): ?OrderInterface {
        $delivery = $this->deliveryManager->createDeliveryFromRecurrenceRule(
            $recurrenceRule,
            $startDate,
            $persist
        );

        if (null === $delivery) {
            return null;
        }

        $pricingStrategy = null;
        if ($arbitraryPriceTemplate = $recurrenceRule->getArbitraryPriceTemplate()) {
            $pricingStrategy = new UseArbitraryPrice(
                new ArbitraryPrice(
                    $arbitraryPriceTemplate['variantName'],
                    $arbitraryPriceTemplate['variantPrice']
                )
            );
        } else {
            $pricingStrategy = new UsePricingRules();
        }

        $order = $this->createOrder($delivery, [
            'pricingStrategy' => $pricingStrategy,
            'persist' => $persist,
            // Display an error when viewing the list of recurrence rules so an admin knows which rules need to be fixed
            // When auto-generating orders, create an incident instead
            'throwException' => $throwException,
        ]);

        if (null !== $order) {
            $order->setSubscription($recurrenceRule);
        }

        if ($persist) {
            $this->entityManager->flush();
        }

        return $order;
    }
}
