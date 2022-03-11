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
use Webmozart\Assert\Assert;

final class SessionSubscriber implements EventSubscriberInterface
{
    /** @var CartContextInterface */
    private $cartContext;

    /** @var string */
    private $sessionKeyName;

    /** @var bool */
    private $enabled;

    /** @var LoggerInterface|null */
    private $logger;

    public function __construct(
        CartContextInterface $cartContext,
        string $sessionKeyName,
        bool $enabled,
        LoggerInterface $logger = null)
    {
        $this->cartContext = $cartContext;
        $this->sessionKeyName = $sessionKeyName;
        $this->enabled = $enabled;
        $this->logger = $logger ?? new NullLogger();
    }

    /**
     * @return array
     */
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

            $cart = $this->cartContext->getCart();

        } catch (CartNotFoundException $exception) {
            $this->logger->debug('SessionSubscriber | No cart found in context');
            return;
        }

        /** @var OrderInterface $cart */
        Assert::isInstanceOf($cart, OrderInterface::class);

        if (!$cart->hasVendor()) {
            $this->logger->debug('SessionSubscriber | No vendor(s) associated to cart');
            return;
        }

        if (null === $cart->getId()) {
            $this->logger->debug('SessionSubscriber | Cart has not been persisted yet');
            return;
        }

        $this->logger->debug(sprintf('SessionSubscriber | Saving cart #%d in session', $cart->getId()));
        $request->getSession()->set($this->sessionKeyName, $cart->getId());
    }
}
