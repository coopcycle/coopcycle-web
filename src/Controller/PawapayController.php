<?php

namespace AppBundle\Controller;

use Doctrine\ORM\EntityManagerInterface;
use AppBundle\Controller\Utils\OrderConfirmTrait;
use AppBundle\Service\OrderManager;
use AppBundle\Service\PawapayManager;
use Psr\Log\LoggerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Sylius\Component\Order\Context\CartContextInterface;
use Sylius\Component\Payment\Model\PaymentInterface;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Contracts\Translation\TranslatorInterface;

class PawapayController extends AbstractController
{
    use OrderConfirmTrait;

    public function __construct(
        private readonly LoggerInterface $logger
    )
    { }

    #[Route(path: '/pawapay/return', name: 'pawapay_return_url')]
    public function returnUrlAction(PawapayManager $pawapayManager,
        CartContextInterface $cartContext,
        EntityManagerInterface $entityManager,
        OrderManager $orderManager,
        TranslatorInterface $translator,
        Request $request)
    {
        $this->logger->info('Pawapay return URL');
        $order = $cartContext->getCart();

        if ($request->query->has('cancel') && $request->query->getBoolean('cancel')) {
            $this->flashPaymentNotCompleted($translator);
            $this->logger->warning(sprintf('Pawapay payment canceled for order "%s"', $order->getNumber()));
            return $this->redirectToRoute('order_payment');
        }

        if (!$request->query->has('depositId')) {
            $this->flashPaymentNotCompleted($translator);
            $this->logger->error(sprintf('Pawapay payment failed for order "%s", no depositId', $order->getNumber()));
            return $this->redirectToRoute('order_payment');
        }

        $depositId = $request->query->get('depositId');

        $payment = $order->getPayments()->filter(fn ($p) => $p->getPawapayDepositId() === $depositId)->first();

        if (!$payment) {
            $this->flashPaymentNotCompleted($translator);
            $this->logger->error(
                sprintf(
                    'Pawapay payment failed for order "%s", no matching payment for depositId "%s"',
                    $order->getNumber(),
                    $depositId
                )
            );
            return $this->redirectToRoute('order_payment');
        }

        $deposit = $pawapayManager->getDeposit($depositId);

        if ($deposit['status'] !== 'COMPLETED') {

           $this->flashPaymentNotCompleted($translator);

            $this->logger->error(
                sprintf(
                    'Pawapay payment failed for order "%s", depositId "%s" not completed',
                    $order->getNumber(),
                    $depositId
                )
            );

            return $this->redirectToRoute('order_payment');
        }

        $orderManager->checkout($order);

        // With pawaPay, the payment is already captured
        $payment->setState(PaymentInterface::STATE_COMPLETED);

        $entityManager->flush();

        return $this->redirectToOrderConfirm($order);
    }

    private function flashPaymentNotCompleted(TranslatorInterface $translator): void
    {
        $this->addFlash(
            'error',
            $translator->trans('pawapay.payment_not_completed')
        );
    }
}
