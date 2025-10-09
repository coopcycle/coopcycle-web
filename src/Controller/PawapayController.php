<?php

namespace AppBundle\Controller;

use Doctrine\ORM\EntityManagerInterface;
use AppBundle\Controller\Utils\OrderConfirmTrait;
use AppBundle\Service\OrderManager;
use AppBundle\Service\PawapayManager;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Sylius\Component\Order\Context\CartContextInterface;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\HttpFoundation\Request;

class PawapayController extends AbstractController
{
    use OrderConfirmTrait;

    #[Route(path: '/pawapay/return', name: 'pawapay_return_url')]
    public function returnUrlAction(PawapayManager $pawapayManager,
        CartContextInterface $cartContext,
        EntityManagerInterface $entityManager,
        OrderManager $orderManager,
        Request $request)
    {
        $order = $cartContext->getCart();

        if ($request->query->has('cancel') && $request->query->getBoolean('cancel')) {
            return $this->redirectToRoute('order_payment');
        }

        // TODO Double-check it is the same deposit / payment

        $deposit = $pawapayManager->getDeposit($request->query->get('depositId'));

        if ($deposit['status'] !== 'COMPLETED') {

            // TODO Add flash message with error

            return $this->redirectToRoute('order_payment');
        }

        $orderManager->checkout($order);

        // TODO Change payment state to completed

        $entityManager->flush();

        return $this->redirectToOrderConfirm($order);
    }
}
