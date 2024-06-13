<?php

namespace AppBundle\Sylius\Cart;

use AppBundle\Business\Context as BusinessContext;
use AppBundle\Service\LoggingUtils;
use AppBundle\Sylius\Order\OrderInterface;
use AppBundle\Sylius\Order\OrderFactory;
use Doctrine\ORM\EntityNotFoundException;
use Psr\Log\LoggerInterface;
use Sylius\Component\Channel\Context\ChannelContextInterface;
use Sylius\Component\Order\Context\CartContextInterface;
use Sylius\Component\Order\Context\CartNotFoundException;
use Sylius\Component\Order\Model\OrderInterface as BaseOrderInterface;
use Sylius\Component\Resource\Factory\FactoryInterface;
use Symfony\Component\Security\Core\Authorization\AuthorizationCheckerInterface;
use Symfony\Component\Security\Core\Security;

final class RestaurantCartContext implements CartContextInterface
{
    /** @var OrderInterface|null */
    private $cart;

    public function __construct(
        private OrderFactory $orderFactory,
        private SessionStorage $storage,
        private ChannelContextInterface $channelContext,
        private RestaurantResolver $resolver,
        private AuthorizationCheckerInterface $authorizationChecker,
        private Security $security,
        private BusinessContext $businessContext,
        private LoggerInterface $checkoutLogger,
        private LoggingUtils $loggingUtils)
    {}

    /**
     * {@inheritdoc}
     */
    public function getCart(): BaseOrderInterface
    {
        if (null !== $this->cart) {
            return $this->cart;
        }

        $cart = null;

        if ($this->storage->has()) {

            $cart = $this->storage->get();

            if (null === $cart || $cart->getChannel()->getCode() !== $this->channelContext->getChannel()->getCode()) {
                $this->storage->remove();
            } else {
                try {
                    if (!$cart->isMultiVendor() && !$cart->getRestaurant()->isEnabled()
                        && !$this->authorizationChecker->isGranted('edit', $cart->getVendor())) {

                        $this->checkoutLogger->info('cart is not valid any more',
                            ['file' => 'RestaurantCartContext', 'order' => $this->loggingUtils->getOrderId($cart)]);

                        $cart = null;
                        $this->storage->remove();
                    }
                } catch (EntityNotFoundException $e) {
                    $this->checkoutLogger->info(sprintf('error: %s', $e->getMessage()),
                        ['file' => 'RestaurantCartContext', 'order' => $this->loggingUtils->getOrderId($cart)]);

                    $cart = null;
                    $this->storage->remove();
                }
            }

            // This happens when the user has a cart stored in session,
            // and is browsing another restaurant.
            // In this case, we want to show an empty cart to the user.
            if (null !== $cart) {
                if ($restaurant = $this->resolver->resolve()) {
                    if (!$this->resolver->accept($cart)) {
                        $this->checkoutLogger->info(sprintf(' cart is not accepted by this restaurant: %d', $restaurant->getId()),
                            ['file' => 'RestaurantCartContext', 'order' => $this->loggingUtils->getOrderId($cart)]);

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

            $this->checkoutLogger->info(sprintf('Order (cart) object created (created_at = %s)', $cart->getCreatedAt()->format(\DateTime::ATOM)),
                ['file' => 'RestaurantCartContext', 'order' => $this->loggingUtils->getOrderId($cart)]);
        }

        if (null === $cart->getCustomer()) {
            if ($user = $this->security->getUser()) {
                $cart->setCustomer($user->getCustomer());
            }
        }

        if ($this->businessContext->isActive()) {
            $cart->setBusinessAccount($this->businessContext->getBusinessAccount());
            // Set default address
            if (null === $cart->getShippingAddress()) {
                $cart->setShippingAddress($this->businessContext->getShippingAddress());
            }
        }

        $this->cart = $cart;

        return $cart;
    }
}
