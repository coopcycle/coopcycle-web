<?php

namespace AppBundle\MessageHandler;

use AppBundle\Entity\Sylius\Order;
use AppBundle\Message\RetrieveStripeFee;
use AppBundle\Service\StripeManager;
use AppBundle\Sylius\Order\AdjustmentInterface;
use Doctrine\ORM\EntityManagerInterface;
use Psr\Log\LoggerInterface;
use Stripe;
use Sylius\Component\Payment\Model\PaymentInterface;
use Sylius\Component\Order\Factory\AdjustmentFactory;
use Symfony\Component\Messenger\Handler\MessageHandlerInterface;

class RetrieveStripeFeeHandler implements MessageHandlerInterface
{
    private EntityManagerInterface $entityManager;
    private StripeManager $stripeManager;
    private AdjustmentFactory $adjustmentFactory;

    public function __construct(
        EntityManagerInterface $entityManager,
        StripeManager $stripeManager,
        AdjustmentFactory $adjustmentFactory,
        LoggerInterface $logger)
    {
        $this->entityManager = $entityManager;
        $this->stripeManager = $stripeManager;
        $this->adjustmentFactory = $adjustmentFactory;
        $this->logger = $logger;
    }

    public function __invoke(RetrieveStripeFee $message)
    {
        $order =
            $this->entityManager->getRepository(Order::class)->findOneByNumber($message->getOrderNumber());

        if (!$order) {
            return;
        }

        $this->stripeManager->configure();

        $lastCompletedPayment = $order->getLastPayment('completed');

        if (!$lastCompletedPayment) {
            $lastPayment = $order->getLastPayment();
            $this->logger->info(sprintf('Last payment is in state "%s", skipping…', $lastPayment->getState()));
            return;
        }

        $stripeUserId = $lastCompletedPayment->getStripeUserId();

        $stripeOptions = [];
        if ($stripeUserId) {
            $stripeOptions['stripe_account'] = $stripeUserId;
        }

        $charge = $this->getCharge($lastCompletedPayment, $stripeOptions);

        if (null === $charge) {
            $this->logger->info('No charge was found');
            return;
        }

        $balanceTransaction =
            Stripe\BalanceTransaction::retrieve($charge->balance_transaction, $stripeOptions);

        $stripeFee = 0;
        foreach ($balanceTransaction->fee_details as $feeDetail) {
            if ('stripe_fee' === $feeDetail->type) {
                $stripeFee = $feeDetail->amount;
                break;
            }
        }

        if ($stripeFee > 0) {

            $order->removeAdjustments(AdjustmentInterface::STRIPE_FEE_ADJUSTMENT);

            $this->logger->info(sprintf('Stripe fee = %d', $stripeFee));

            $stripeFeeAdjustment = $this->adjustmentFactory->createWithData(
                AdjustmentInterface::STRIPE_FEE_ADJUSTMENT,
                'Stripe fee',
                $stripeFee,
                $neutral = true
            );
            $order->addAdjustment($stripeFeeAdjustment);

            $this->entityManager->flush();
        }
    }

    /**
     * @return Stripe\StripeObject|null
     */
    private function getCharge(PaymentInterface $payment, array $stripeOptions): ?Stripe\StripeObject
    {
        $paymentIntent = $payment->getPaymentIntent();

        if (null !== $paymentIntent) {

            $intent = Stripe\PaymentIntent::retrieve($paymentIntent, $stripeOptions);

            if (count($intent->charges->data) === 1) {
                return current($intent->charges->data);
            }

            return null;
        }

        return Stripe\Charge::retrieve($payment->getCharge(), $stripeOptions);
    }
}

