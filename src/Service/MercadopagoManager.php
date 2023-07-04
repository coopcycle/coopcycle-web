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
    private $logger;

    public function __construct(
        SettingsManager $settingsManager,
        LoggerInterface $logger)
    {
        $this->settingsManager = $settingsManager;
        $this->logger = $logger;
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
        $order = $payment->getOrder();
        $restaurant = $order->getRestaurant();

        $options = [];

        $applicationFee = 0;
        $accessToken = null;
        if (null !== $restaurant) {
            $account = $restaurant->getMercadopagoAccount();
            if ($account) {
                $applicationFee = $order->getFeeTotal();
                $accessToken = $account->getAccessToken();
            }
        }

        if (null !== $accessToken) {
            MercadoPago\SDK::setAccessToken($accessToken);
        } else {
            $this->configure();
        }

        $order = $payment->getOrder();

        $p = new MercadoPago\Payment();

        $p->transaction_amount = ($payment->getAmount() / 100);
        $p->token = $payment->getStripeToken();
        $p->description = sprintf('Order %s', $order->getNumber());
        $p->installments = $payment->getMercadopagoInstallments() ?? 1;
        $p->payment_method_id = $payment->getMercadopagoPaymentMethod();

        $p->payer = array(
            'email' => $order->getCustomer()->getEmail() // this email must be the same as the one entered in the payment form
        );

        $mpPaymentMethod = $this->getPaymentMethod($payment);

        if (null !== $mpPaymentMethod && $mpPaymentMethod->deferred_capture === "supported") {
            $p->capture = false;
        } else {
            $p->capture = true;
        }

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
        // FIXME: should be refactored

        $order = $payment->getOrder();

        $options = [];

        $accessToken = null;
        if (null !== $order->getRestaurant()) {
            $account = $order->getRestaurant()->getMercadopagoAccount();
            if ($account) {
                $accessToken = $account->getAccessToken();
            }
        }

        if (null !== $accessToken) {
            MercadoPago\SDK::setAccessToken($accessToken);
        } else {
            $this->configure();
        }

        $payment = MercadoPago\Payment::read(["id" => $payment->getCharge()]);

        if (!$payment->capture) {
            $payment->capture = true;

            if (!$payment->update()) {
                throw new \Exception((string) $payment->error);
            }
        }

        return $payment;
    }

    /**
     * @return MercadoPago\Payment
     */
    public function getPayment(PaymentInterface $payment)
    {
        if ($this->settingsManager->isMercadopagoLivemode()) {
            $this->configure();
        } else {
            MercadoPago\SDK::setAccessToken($this->settingsManager->get('mercadopago_access_token_for_test'));
        }

        $order = $payment->getOrder();

        $options = [];

        if (null !== $order->getRestaurant()) {
            $account = $order->getRestaurant()->getMercadopagoAccount();
            if ($account) {
                // @see MercadoPago\Manager::processOptions()
                $options['custom_access_token'] = $account->getAccessToken();
            }
        }

        return MercadoPago\Payment::read(["id" => $payment->getMercadopagoPaymentId()], ["custom_access_token" => $options['custom_access_token']]);
    }

    public function getPaymentMethod(PaymentInterface $payment)
    {
        try {
            $paymentMethods = MercadoPago\PaymentMethod::all();
            foreach($paymentMethods as $paymentMethod) {
                if ($paymentMethod->id === $payment->getMercadopagoPaymentMethod()) {
                    return $paymentMethod;
                }
            }
        } catch(\Exception $e) {
            $this->logger->error(
                sprintf('Mercadopago - Error %s while tryinh to read payment method with id %s', $e->getMessage(), $payment->getMercadopagoPaymentMethod())
            );
            return null;
        }

        $this->logger->error(
            sprintf('Mercadopago - Error MercadoPago\PaymentMethod not found for payment method with id %s', $payment->getMercadopagoPaymentMethod())
        );
        return null;
    }
}
