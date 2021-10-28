<?php

namespace AppBundle\Service;

use Psr\Log\LoggerInterface;
use MercadoPago;
use Sylius\Component\Payment\Model\PaymentInterface;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;

/**
 * @see https://www.mercadopago.com.mx/developers/es/guides/payments/api/other-features
 */
class MercadopagoManager
{
    private $settingsManager;

    public function __construct(
        SettingsManager $settingsManager)
    {
        $this->settingsManager = $settingsManager;
    }

    public function configure()
    {
        MercadoPago\SDK::setAccessToken($this->settingsManager->get('mercadopago_access_token'));
    }

    /**
     * @return MercadoPago\Payment
     */
    public function authorize(PaymentInterface $payment)
    {
        $this->configure();

        $order = $payment->getOrder();
        $restaurant = $order->getRestaurant();

        $options = [];

        $applicationFee = 0;
        if (null !== $restaurant) {
            $account = $restaurant->getMercadopagoAccount();
            if ($account) {
                $applicationFee = $order->getFeeTotal();
                // @see MercadoPago\Manager::processOptions()
                $options['custom_access_token'] = $account->getAccessToken();
            }
        }

        $order = $payment->getOrder();

        $p = new MercadoPago\Payment();

        $p->transaction_amount = ($payment->getAmount() / 100);
        $p->token = $payment->getStripeToken();
        $p->description = sprintf('Order %s', $order->getNumber());
        $p->installments = $payment->getMercadopagoInstallments() ?? 1;
        $p->payment_method_id = $payment->getMercadopagoPaymentMethod();

        $payer = new MercadoPago\Payer();
        $payer->email = $order->getCustomer()->getEmail(); // this email must be the same as the one entered in the payment form
        $p->payer = $payer;

        $p->capture = false;

        if ($applicationFee > 0) {
            $p->application_fee = ($applicationFee / 100);
        }

        if (!$p->save($options)) {
            throw new \Exception((string) $p->error);
        }

        return $p;
    }

    /**
     * @return MercadoPago\Payment
     */
    public function capture(PaymentInterface $payment)
    {
        $this->configure();

        // FIXME: should be refactored

        $order = $payment->getOrder();

        $options = [];

        if (null !== $order->getRestaurant()) {
            $account = $order->getRestaurant()->getMercadopagoAccount();
            if ($account) {
                // @see MercadoPago\Manager::processOptions()
                $options['custom_access_token'] = $account->getAccessToken();
            }
        }

        $payment = MercadoPago\Payment::read(["id" => $payment->getCharge()], ["custom_access_token" => $options['custom_access_token']]);
        $payment->capture = true;

        if (!$payment->update()) {
            throw new \Exception((string) $payment->error);
        }

        return $payment;
    }

    /**
     * @return MercadoPago\Payment
     */
    public function getPayment(PaymentInterface $payment)
    {
        $this->configure();

        $order = $payment->getOrder();

        $options = [];

        if (null !== $order->getRestaurant()) {
            $account = $order->getRestaurant()->getMercadopagoAccount(true);
            if ($account) {
                // @see MercadoPago\Manager::processOptions()
                $options['custom_access_token'] = $account->getAccessToken();
            }
        }

        return MercadoPago\Payment::read(["id" => $payment->getMercadopagoPaymentId()], ["custom_access_token" => $options['custom_access_token']]);
    }
}
