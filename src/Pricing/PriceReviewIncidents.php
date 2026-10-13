<?php

namespace AppBundle\Pricing;

use AppBundle\Action\Incident\CreateIncident;
use AppBundle\Entity\Incident\Incident;
use AppBundle\Entity\User;
use AppBundle\Sylius\Order\OrderInterface;
use Doctrine\ORM\EntityManagerInterface;
use Psr\Log\LoggerInterface;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Contracts\Translation\TranslatorInterface;

/**
 * An order whose price could not be calculated gets a placeholder price of 0,
 * and an incident so that a dispatcher reviews it.
 *
 * The incident can not be created while the price is being set: the order may
 * still be new, and its number, which the incident refers to, is only assigned
 * once it has been written. Orders are collected here instead, and the pending
 * ones are created at the end of the request, the command or the message
 * being handled.
 *
 * @see PriceReviewIncidentsSubscriber
 */
class PriceReviewIncidents
{
    /**
     * @var \SplObjectStorage<OrderInterface, null>
     */
    private \SplObjectStorage $pending;

    public function __construct(
        private readonly EntityManagerInterface $entityManager,
        private readonly TranslatorInterface $translator,
        private readonly Security $security,
        private readonly CreateIncident $createIncident,
        private readonly LoggerInterface $feeCalculationLogger
    ) {
        $this->pending = new \SplObjectStorage();
    }

    public function schedule(OrderInterface $order): void
    {
        $this->pending->attach($order);
    }

    public function discardPending(): void
    {
        $this->pending = new \SplObjectStorage();
    }

    public function createPending(): void
    {
        if (0 === count($this->pending)) {
            return;
        }

        $orders = iterator_to_array($this->pending);
        // Drained first: an incident that can not be created is not retried
        // on the next request handled by this process
        $this->discardPending();

        if (!$this->entityManager->isOpen()) {
            $this->feeCalculationLogger->warning(
                'Could not create price review incidents, the entity manager is closed',
                ['orders' => count($orders)]
            );

            return;
        }

        foreach ($orders as $order) {
            try {
                $this->createFor($order);
            } catch (\Exception $e) {
                // Best effort: the price itself has been set, and one order
                // must not prevent the others from getting an incident
                $this->feeCalculationLogger->error('Could not create a price review incident', [
                    'order' => $order->getId(),
                    'exception' => $e->getMessage(),
                ]);
            }
        }
    }

    private function createFor(OrderInterface $order): void
    {
        // Never written: a quote, or a request that failed before the flush
        if (null === $order->getId()) {
            return;
        }

        $task = $order->getDelivery()?->getPickup();

        if (null === $task || null === $task->getId()) {
            $this->feeCalculationLogger->warning(
                'Could not create a price review incident, the order has no persisted pickup task',
                ['order' => $order->getId()]
            );

            return;
        }

        $user = $this->security->getUser();

        $isUserWithAccount = $user instanceof User && null !== $user->getId();
        // If it's not a user with an account, it could be an ApiApp
        // ApiKey: see BearerTokenAuthenticator
        // OAuth client: League\Bundle\OAuth2ServerBundle\Security\User\NullUser

        $title = $this->translator->trans('form.delivery.price.missing.incident', [
            '%number%' => $order->getNumber(),
        ]);

        //FIXME: allow to set $createdBy API clients (ApiApp) and integrations; see Incident::createdBy
        if (!$isUserWithAccount) {
            $title = $title.' (API client)';
        }

        $incident = new Incident();
        $incident->setTitle($title);
        $incident->setFailureReasonCode('PRICE_REVIEW_NEEDED');
        $incident->setTask($task);

        $this->createIncident->__invoke(
            $incident,
            $isUserWithAccount ? $user : null
        );
    }
}
