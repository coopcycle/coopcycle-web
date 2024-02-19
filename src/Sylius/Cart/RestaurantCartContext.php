<?php

namespace AppBundle\Sylius\Cart;

use AppBundle\Service\LoggingUtils;
use AppBundle\Sylius\Order\OrderInterface;
use Doctrine\ORM\EntityNotFoundException;
use Psr\Log\LoggerInterface;
use Sylius\Component\Channel\Context\ChannelContextInterface;
use Sylius\Component\Order\Context\CartContextInterface;
use Sylius\Component\Order\Context\CartNotFoundException;
use Sylius\Component\Order\Model\OrderInterface as BaseOrderInterface;
use Sylius\Component\Order\Repository\OrderRepositoryInterface;
use Sylius\Component\Resource\Factory\FactoryInterface;
use Symfony\Component\HttpFoundation\Session\SessionInterface;
use Symfony\Component\Security\Core\Authorization\AuthorizationCheckerInterface;
use Symfony\Component\Security\Core\Security;

final class RestaurantCartContext implements CartContextInterface
{
    private $session;

    private $orderRepository;

    private $orderFactory;

    private $sessionKeyName;

    /**
     * @var ChannelContextInterface
     */
    private ChannelContextInterface $channelContext;

    /**
     * @var AuthorizationCheckerInterface
     */
    private AuthorizationCheckerInterface $authorizationChecker;

    private Security $security;

    /** @var OrderInterface|null */
    private $cart;

    /**
     * @param SessionInterface $session
     * @param OrderRepositoryInterface $orderRepository
     * @param string $sessionKeyName
     */
    public function __construct(
        SessionInterface $session,
        OrderRepositoryInterface $orderRepository,
        FactoryInterface $orderFactory,
        string $sessionKeyName,
        ChannelContextInterface $channelContext,
        RestaurantResolver $resolver,
        AuthorizationCheckerInterface $authorizationChecker,
        Security $security,
        private LoggerInterface $checkoutLogger,
        private LoggingUtils $loggingUtils
    )
    {
        $this->session = $session;
        $this->orderRepository = $orderRepository;
        $this->orderFactory = $orderFactory;
        $this->sessionKeyName = $sessionKeyName;
        $this->channelContext = $channelContext;
        $this->resolver = $resolver;
        $this->authorizationChecker = $authorizationChecker;
        $this->security = $security;
    }

    /**
     * {@inheritdoc}
     */
    public function getCart(): BaseOrderInterface
    {
        if (null !== $this->cart) {
            return $this->cart;
        }

        $cart = null;

        if ($this->session->has($this->sessionKeyName)) {

            $cart = $this->orderRepository->findCartById($this->session->get($this->sessionKeyName));

            if (null === $cart || $cart->getChannel()->getCode() !== $this->channelContext->getChannel()->getCode()) {
                $this->session->remove($this->sessionKeyName);
            } else {
                try {
                    if (!$cart->isMultiVendor() && !$cart->getRestaurant()->isEnabled()
                        && !$this->authorizationChecker->isGranted('edit', $cart->getVendor())) {
                        $cart = null;
                        $this->session->remove($this->sessionKeyName);
                    }
                } catch (EntityNotFoundException $e) {
                    $cart = null;
                    $this->session->remove($this->sessionKeyName);
                }
            }

            // This happens when the user has a cart stored in session,
            // and is browsing another restaurant.
            // In this case, we want to show an empty cart to the user.
            if (null !== $cart) {
                if ($restaurant = $this->resolver->resolve()) {
                    if (!$this->resolver->accept($cart)) {
                        $cart->clearItems();
                        $cart->setShippingTimeRange(null);
                    }
                }
            }
        }

        if (null === $cart) {

            $restaurant = $this->resolver->resolve();

            if (null === $restaurant) {

                throw new CartNotFoundException('No restaurant could be resolved from request.');
            }

            $cart = $this->orderFactory->createForRestaurant($restaurant);

            $this->checkoutLogger->info(sprintf('Order (cart) object created (created_at = %s) | RestaurantCartContext | called by %s',
                $cart->getCreatedAt()->format(\DateTime::ATOM), $this->loggingUtils->getBacktrace()));
        }

        if (null === $cart->getCustomer()) {
            if ($user = $this->security->getUser()) {
                $cart->setCustomer($user->getCustomer());
            }
        }

        $this->cart = $cart;

        return $cart;
    }
}
