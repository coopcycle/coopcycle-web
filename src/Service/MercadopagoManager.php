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
    private $urlGenerator;
    private $secret;
    private $logger;

    public function __construct(
        SettingsManager $settingsManager,
        UrlGeneratorInterface $urlGenerator,
        string $secret,
        LoggerInterface $logger)
    {
        $this->settingsManager = $settingsManager;
        $this->urlGenerator = $urlGenerator;
        $this->secret = $secret;
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
        $this->configure();

        $order = $payment->getOrder();

        $options = [];

        $applicationFee = 0;
        if (null !== $order->getRestaurant()) {
            $account = $order->getRestaurant()->getMercadopagoAccount(false);
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
        $p->payer = array(
            'email' => $order->getCustomer()->getEmail()
        );
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

        $payment = MercadoPago\Payment::find_by_id($payment->getCharge());
        $payment->capture = true;

        $payment->update();

        return $payment;
    }
}
