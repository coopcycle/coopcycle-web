<?php

namespace AppBundle\Sylius\Cart;

use AppBundle\Sylius\Order\OrderInterface;
use Psr\Log\LoggerInterface;
use Psr\Log\NullLogger;
use Sylius\Component\Order\Context\CartContextInterface;
use Sylius\Component\Order\Context\CartNotFoundException;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\HttpKernel\Event\ResponseEvent;
use Symfony\Component\HttpKernel\KernelEvents;

final class SessionSubscriber implements EventSubscriberInterface
{
    /** @var CartContextInterface */
    private $cartContext;

    /** @var SessionStorage */
    private $storage;

    /** @var bool */
    private $enabled;

    /** @var LoggerInterface|null */
    private $logger;

    public function __construct(
        CartContextInterface $cartContext,
        SessionStorage $storage,
        bool $enabled,
        LoggerInterface $logger = null)
    {
        $this->cartContext = $cartContext;
        $this->storage = $storage;
        $this->enabled = $enabled;
        $this->logger = $logger ?? new NullLogger();
    }

    public static function getSubscribedEvents(): array
    {
        return [
            KernelEvents::RESPONSE => ['onKernelResponse'],
        ];
    }

    public function onKernelResponse(ResponseEvent $event): void
    {
        if (!$this->enabled) {
            return;
        }

        if (!$event->isMainRequest()) {
            return;
        }

        $request = $event->getRequest();

        if (!$request->hasPreviousSession() || !$request->getSession()->isStarted()) {
            $this->logger->debug('SessionSubscriber | No session, skipping');
            return;
        }

        try {

            /** @var OrderInterface $cart */
            $cart = $this->cartContext->getCart();

        } catch (CartNotFoundException $exception) {
            $this->logger->debug('SessionSubscriber | No cart found in context');
            return;
        }

        if (null === $cart->getId()) {
            $this->logger->debug(sprintf('SessionSubscriber | Order (cart) (created_at = %s) has not been persisted yet', $cart->getCreatedAt()->format(\DateTime::ATOM)));
            return;
        }

        if (!$cart->hasVendor()) {
            $this->logger->debug(sprintf('SessionSubscriber | Order #%d | No vendor(s) associated to cart', $cart->getId()));
            return;
        }

        $this->logger->debug(sprintf('SessionSubscriber | Order #%d | Saving in session', $cart->getId()));
        $this->storage->set($cart);
    }
}
