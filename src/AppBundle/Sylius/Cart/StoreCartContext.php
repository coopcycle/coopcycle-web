<?php

namespace AppBundle\Sylius\Cart;

use Doctrine\ORM\EntityRepository;
use Sylius\Component\Order\Context\CartContextInterface;
use Sylius\Component\Order\Context\CartNotFoundException;
use Sylius\Component\Order\Model\OrderInterface;
use Sylius\Component\Order\Repository\OrderRepositoryInterface;
use Sylius\Component\Resource\Factory\FactoryInterface;
use Symfony\Component\HttpFoundation\Session\SessionInterface;

final class StoreCartContext implements CartContextInterface
{
    private $session;

    private $orderRepository;

    private $orderFactory;

    private $restaurantRepository;

    private $sessionKeyName;

    /**
     * @param SessionInterface $session
     * @param OrderRepositoryInterface $orderRepository
     * @param string $sessionKeyName
     */
    public function __construct(
        SessionInterface $session,
        OrderRepositoryInterface $orderRepository,
        FactoryInterface $orderFactory,
        EntityRepository $storeRepository,
        string $storeCartSessionKeyName)
    {
        $this->session = $session;
        $this->orderRepository = $orderRepository;
        $this->orderFactory = $orderFactory;
        $this->storeRepository = $storeRepository;
        $this->sessionKeyName = $storeCartSessionKeyName;
    }

    /**
     * {@inheritdoc}
     */
    public function getCart(): OrderInterface
    {
        if (!$this->session->has('storeId')) {
            throw new CartNotFoundException('There is no restaurant in session');
        }

        if (!$this->session->has($this->sessionKeyName)) {
            $store = $this->storeRepository->find($this->session->get('storeId'));

            return $this->orderFactory->createForStore($store);
        }

        $cart = $this->orderRepository->findCartById($this->session->get($this->sessionKeyName));

        if (null === $cart) {
            $this->session->remove($this->sessionKeyName);

            throw new CartNotFoundException('Unable to find the cart in session');
        }

        return $cart;
    }
}
