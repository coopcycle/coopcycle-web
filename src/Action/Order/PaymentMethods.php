<?php

namespace AppBundle\Action\Order;

use AppBundle\Api\Dto\PaymentMethodsOutput;
use AppBundle\Payment\GatewayResolver;
use AppBundle\Service\PaygreenManager;
use AppBundle\Service\SettingsManager;
use Sylius\Component\Payment\Model\PaymentInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;

class PaymentMethods
{
    private $cashEnabled;
    private $edenredEnabled;

    public function __construct(
        private SettingsManager $settingsManager,
        private GatewayResolver $gatewayResolver,
        private PaygreenManager $paygreenManager,
        bool $cashEnabled,
        bool $edenredEnabled)
    {
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

        if (!$data->isMultiVendor() && 'paygreen' === $this->gatewayResolver->resolveForOrder($data)) {
            $paygreenPlatforms = $this->paygreenManager->getEnabledPlatforms($data->getRestaurant()->getPaygreenShopId());
            if (in_array('restoflash', $paygreenPlatforms)) {
                $output->addMethod('restoflash');
            }
            if (in_array('conecs', $paygreenPlatforms)) {
                $output->addMethod('conecs');
            }
            if (in_array('swile', $paygreenPlatforms)) {
                $output->addMethod('swile');
            }
        }

        return $output;
    }
}
