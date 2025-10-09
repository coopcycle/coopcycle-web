<?php

namespace AppBundle\Controller;

use Doctrine\ORM\EntityManagerInterface;
use AppBundle\Controller\Utils\OrderConfirmTrait;
use AppBundle\Service\OrderManager;
use AppBundle\Service\PawapayManager;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Sylius\Component\Order\Context\CartContextInterface;
use Sylius\Component\Payment\Model\PaymentInterface;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Contracts\Translation\TranslatorInterface;

class PawapayController extends AbstractController
{
    use OrderConfirmTrait;

    #[Route(path: '/pawapay/return', name: 'pawapay_return_url')]
    public function returnUrlAction(PawapayManager $pawapayManager,
        CartContextInterface $cartContext,
        EntityManagerInterface $entityManager,
        OrderManager $orderManager,
        TranslatorInterface $translator,
        Request $request)
    {
        $order = $cartContext->getCart();

        if ($request->query->has('cancel') && $request->query->getBoolean('cancel')) {
            return $this->redirectToRoute('order_payment');
        }

        if (!$request->query->has('depositId')) {
            return $this->redirectToRoute('order_payment');
        }

        $depositId = $request->query->get('depositId');

        $payment = $order->getPayments()->filter(fn ($p) => $p->getPawapayDepositId() === $depositId);

        if (!$payment) {
            return $this->redirectToRoute('order_payment');
        }

        $deposit = $pawapayManager->getDeposit($depositId);

        if ($deposit['status'] !== 'COMPLETED') {

            $this->addFlash(
                'error',
                $translator->trans('pawapay.payment_not_completed')
            );

            return $this->redirectToRoute('order_payment');
        }

        $orderManager->checkout($order);

        // With pawaPay, the payment is already captured
        $payment->setState(PaymentInterface::STATE_COMPLETED);

        $entityManager->flush();

        return $this->redirectToOrderConfirm($order);
    }
}
