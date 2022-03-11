<?php

namespace AppBundle\EventListener;

use AppBundle\Sylius\Order\OrderInterface;
use Doctrine\ORM\EntityManagerInterface;
use Nucleos\UserBundle\NucleosUserEvents;
use Nucleos\UserBundle\Event\UserEvent;
use Nucleos\UserBundle\Model\UserInterface;
use Sylius\Component\Order\Context\CartContextInterface;
use Sylius\Component\Order\Context\CartNotFoundException;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\Security\Http\Event\InteractiveLoginEvent;
use Symfony\Component\Security\Http\SecurityEvents;

/**
 * Sets the cart customer when authentication events occur.
 *
 * @see https://github.com/Sylius/Sylius/blob/master/src/Sylius/Bundle/CoreBundle/EventListener/CartBlamerListener.php
 */
final class WebAuthenticationListener implements EventSubscriberInterface
{
    private $cartManager;
    private $cartContext;

    public function __construct(EntityManagerInterface $cartManager, CartContextInterface $cartContext)
    {
        $this->cartManager = $cartManager;
        $this->cartContext = $cartContext;
    }

    /**
     * @return array
     */
    public static function getSubscribedEvents()
    {
        return [
            NucleosUserEvents::SECURITY_IMPLICIT_LOGIN => 'onImplicitLogin',
            SecurityEvents::INTERACTIVE_LOGIN => 'onSecurityInteractiveLogin',
        ];
    }

    public function onImplicitLogin(UserEvent $event)
    {
        $user = $event->getUser();
        if ($user instanceof UserInterface) {
            $this->blame($user);
        }
    }

    public function onSecurityInteractiveLogin(InteractiveLoginEvent $event)
    {
        $user = $event->getAuthenticationToken()->getUser();
        if ($user instanceof UserInterface) {
            $this->blame($user);
        }
    }

    private function blame(UserInterface $user): void
    {
        $cart = $this->getCart();
        if (null === $cart) {
            return;
        }

        $cart->setCustomer($user->getCustomer());

        if (!$cart->hasVendor()) {
            return;
        }

        $this->cartManager->persist($cart);
        $this->cartManager->flush();
    }

    private function getCart(): ?OrderInterface
    {
        try {
            $cart = $this->cartContext->getCart();
        } catch (CartNotFoundException $exception) {
            return null;
        }

        return $cart;
    }
}
