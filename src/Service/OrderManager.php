<?php

namespace AppBundle\Service;

use AppBundle\Domain\Order\Command as OrderCommand;
use AppBundle\Entity\Refund;
use AppBundle\Entity\Sylius\OrderBookmark;
use AppBundle\Sylius\Order\OrderInterface;
use Doctrine\ORM\EntityManagerInterface;
use SimpleBus\SymfonyBridge\Bus\CommandBus;
use SM\Factory\FactoryInterface as StateMachineFactoryInterface;
use Sylius\Component\Payment\Model\PaymentInterface;
use Sylius\Component\Payment\PaymentTransitions;
use Symfony\Component\Security\Core\Security;

class OrderManager
{
    public function __construct(
        private readonly StateMachineFactoryInterface $stateMachineFactory,
        private readonly CommandBus $commandBus,
        private readonly EntityManagerInterface $entityManager,
        private readonly Security $security)
    {
    }

    public function accept(OrderInterface $order)
    {
        $this->commandBus->handle(new OrderCommand\AcceptOrder($order));
    }

    public function refuse(OrderInterface $order, $reason = null)
    {
        $this->commandBus->handle(new OrderCommand\RefuseOrder($order, $reason));
    }

    /**
     * @param OrderInterface $order
     * @param string|array|null $data
     */
    public function checkout(OrderInterface $order, $data = null)
    {
        $this->commandBus->handle(new OrderCommand\Checkout($order, $data));
    }

    public function quote(OrderInterface $order)
    {
        $this->commandBus->handle(new OrderCommand\Quote($order));
    }

    public function fulfill(OrderInterface $order)
    {
        $this->commandBus->handle(new OrderCommand\Fulfill($order));
    }

    public function cancel(OrderInterface $order, $reason = null)
    {
        $this->commandBus->handle(new OrderCommand\CancelOrder($order, $reason));
    }

    public function startPreparing(OrderInterface $order)
    {
        $this->commandBus->handle(new OrderCommand\StartPreparingOrder($order));
    }

    public function finishPreparing(OrderInterface $order)
    {
        $this->commandBus->handle(new OrderCommand\FinishPreparingOrder($order));
    }

    public function onDemand(OrderInterface $order)
    {
        $this->commandBus->handle(new OrderCommand\OnDemand($order));
    }

    public function delay(OrderInterface $order, $delay = 10)
    {
        $this->commandBus->handle(new OrderCommand\DelayOrder($order, $delay));
    }

    public function completePayment(PaymentInterface $payment)
    {
        $stateMachine = $this->stateMachineFactory->get($payment, PaymentTransitions::GRAPH);
        $stateMachine->apply(PaymentTransitions::TRANSITION_COMPLETE);
    }

    public function refundPayment(PaymentInterface $payment, $amount = null, $liableParty = Refund::LIABLE_PARTY_PLATFORM, $comments = '')
    {
        $this->commandBus->handle(new OrderCommand\Refund($payment, $amount, $liableParty, $comments));
    }

    public function restore(OrderInterface $order)
    {
        $this->commandBus->handle(new OrderCommand\RestoreOrder($order));
    }

    private function getBookmark(OrderInterface $order): ?OrderBookmark
    {
        $user = $this->security->getUser();

        $bookmarks = $order->getBookmarks()->filter(function ($bookmark) use ($user) {
            return $bookmark->getOwner() === $user || ($bookmark->getRole() && in_array($bookmark->getRole(), $user->getRoles()));
        });

        return $bookmarks->first() ?: null;
    }

    public function hasBookmark(OrderInterface $order): bool
    {
        return $this->getBookmark($order) !== null;
    }

    public function setBookmark(OrderInterface $order, bool $isBookmarked): void
    {
        $user = $this->security->getUser();
        $roles = $user->getRoles();

        //Only admins can bookmark orders at the moment
        if (!in_array('ROLE_ADMIN', $roles)) {
            return;
        }

        if ($isBookmarked) {
            if (!$this->hasBookmark($order)) {
                $bookmark = new OrderBookmark($order, $user, 'ROLE_ADMIN');
                $this->entityManager->persist($bookmark);
            }
        } else {
            if ($this->hasBookmark($order)) {
                $bookmark = $this->getBookmark($order);
                $this->entityManager->remove($bookmark);
            }
        }
    }
}
