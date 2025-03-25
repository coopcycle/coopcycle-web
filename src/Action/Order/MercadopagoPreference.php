<?php

namespace AppBundle\Action\Order;

use MercadoPago\Client\Common\RequestOptions;
use MercadoPago\MercadoPagoConfig;
use MercadoPago\Client\Preference\PreferenceClient;
use MercadoPago\Resources\Preference;
use Ramsey\Uuid\Uuid;
use Symfony\Component\HttpFoundation\Request;
use AppBundle\Payment\MercadopagoPreferenceResponse;

class MercadopagoPreference
{
    /**
     * @see https://www.mercadopago.com/developers/en/reference/preferences/_checkout_preferences/post
     */
    public function __invoke($data, Request $request): Preference
    {
        $account = $data->getRestaurant()->getMercadopagoAccount();
        if ($account) {
            MercadoPagoConfig::setAccessToken($account->getAccessToken());
        }

        $client = new PreferenceClient();

        $requestOptions = new RequestOptions();
        $requestOptions->setCustomHeaders([
            sprintf('X-Idempotency-Key: %s', Uuid::uuid4()->toString())
        ]);

        return $client->create([
            'items' => [
                'id' => $data->getNumber(),
                // This is the item's title, which will display during the payment process, at checkout, activities, and emails.
                'title' => sprintf('Pedido %s', $data->getNumber()),
                // what user/buyer can see when is paying in MP's screens
                // 'description' => $data->getRestaurant()->getName(),
                'quantity' => 1,
                'unit_price' => ($data->getTotal() / 100),
            ],
            'payer' => [
                'email' => $data->getCustomer()->getEmail(),
            ],
            'marketplace_fee' => ($data->getFeeTotal() / 100),

        ], $requestOptions);
    }
}
