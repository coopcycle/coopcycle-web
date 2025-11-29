<?php

namespace AppBundle\Api\State;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProcessorInterface;
use AppBundle\Api\Dto\CartSessionInput;
use AppBundle\Api\Resource\CartSession;
use AppBundle\Sylius\Order\OrderFactory;
use AppBundle\Security\OrderAccessTokenManager;
use AppBundle\Service\LoggingUtils;
use Doctrine\ORM\EntityManagerInterface;
use Lexik\Bundle\JWTAuthenticationBundle\Security\Authenticator\Token\JWTPostAuthenticationToken;
use Psr\Log\LoggerInterface;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\Security\Core\Authentication\Token\Storage\TokenStorageInterface;

class CartSessionProcessor implements ProcessorInterface
{
    public function __construct(
        private readonly OrderFactory $orderFactory,
        private readonly TokenStorageInterface $tokenStorage,
        private readonly OrderAccessTokenManager $orderAccessTokenManager,
        private readonly EntityManagerInterface $entityManager,
        private readonly LoggerInterface $checkoutLogger,
        private readonly LoggingUtils $loggingUtils
    )
    {
    }

    private function getCartFromSession()
    {
        if (null !== $token = $this->tokenStorage->getToken()) {
            if ($token instanceof JWTPostAuthenticationToken && $token->hasAttribute('cart')) {
                return $token->getAttribute('cart');
            }
        }
    }

    private function getUserFromToken()
    {
        if (null !== $token = $this->tokenStorage->getToken()) {
            if ($token instanceof JWTPostAuthenticationToken && is_object($token->getUser())) {
                return $token->getUser();
            }
        }
    }

    public function process($data, Operation $operation, array $uriVariables = [], array $context = [])
    {
        $session = new CartSession();

        if ($cart = $this->getCartFromSession()) {
            $cart->setRestaurant($data->restaurant);
        } else {
            $cart = $this->orderFactory->createForRestaurant($data->restaurant);

            $this->checkoutLogger->info(sprintf('Order (cart) object created (created_at = %s) | CartSessionInputDataTransformer',
                $cart->getCreatedAt()->format(\DateTime::ATOM)));
        }

        // TODO When in business context
        // - Associate to the business account with setBusinessAccount
        // - Set default address if not set

        if (null === $cart->getCustomer() && $user = $this->getUserFromToken()) {
            $cart->setCustomer($user->getCustomer());
        }

        if ($data->shippingAddress) {
            $isNewAddress = null === $data->shippingAddress->getId();

            // When this is an existing address,
            // make sure it belongs to the customer, if any
            $addressBelongsToCustomer = !$isNewAddress
                && (null !== $cart->getCustomer() && $cart->getCustomer()->hasAddress($data->shippingAddress));

            if ($isNewAddress || $addressBelongsToCustomer) {
                $cart->setShippingAddress($data->shippingAddress);
            }
        }

        $this->entityManager->persist($cart);
        $this->entityManager->flush();

        $isExisting = $cart->getId() === null;

        if ($isExisting) {
            $this->checkoutLogger->info('Order updated in the database', ['file' => 'CreateSession', 'order' => $this->loggingUtils->getOrderId($cart)]);
        } else {
            $this->checkoutLogger->info(sprintf('Order #%d (created_at = %s) created in the database',
                $cart->getId(), $cart->getCreatedAt()->format(\DateTime::ATOM)), ['file' => 'CreateSession', 'order' => $this->loggingUtils->getOrderId($cart)]);
        }

        $session->token = $this->orderAccessTokenManager->create($cart);
        $session->cart = $cart;

        return $session;
    }
}
