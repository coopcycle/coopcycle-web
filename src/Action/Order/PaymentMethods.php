<?php

namespace AppBundle\Action\Order;

use AppBundle\Api\Dto\PaymentMethodsOutput;
use AppBundle\Service\SettingsManager;
use Sylius\Component\Payment\Model\PaymentInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;

class PaymentMethods
{
    private $settingsManager;
    private $cashEnabled;
    private $edenredEnabled;

    public function __construct(
        SettingsManager $settingsManager,
        bool $cashEnabled,
        bool $edenredEnabled)
    {
        $this->settingsManager = $settingsManager;
        $this->cashEnabled = $cashEnabled;
        $this->edenredEnabled = $edenredEnabled;
    }

    public function __invoke($data): PaymentMethodsOutput
    {
        $output = new PaymentMethodsOutput();

        if ($this->settingsManager->supportsCardPayments()) {
            $output->addMethod('card');
        }

        if ($this->cashEnabled || $data->supportsCashOnDelivery()) {
            $output->addMethod('cash_on_delivery');
        }

        if ($this->edenredEnabled || $data->supportsEdenred()) {
            // TODO Also check if balance is > 0
            $output->addMethod('edenred');
        }

        return $output;
    }
}
