<?php

namespace AppBundle\Action\Order;

use MercadoPago;
use Symfony\Component\HttpFoundation\Request;
use AppBundle\Payment\MercadopagoPreferenceResponse;

class MercadopagoPreference
{
    /**
     * @return MercadoPago\Preference
     */
    public function __invoke($data, Request $request)
    {
        $account = $data->getRestaurant()->getMercadopagoAccount();
        if ($account) {
            MercadoPago\SDK::setAccessToken($account->getAccessToken());
        }

        // Create a preference object
        $preference = new MercadoPago\Preference();

        // Create items for the preference
        // We add one item for the whole order
        $items = [];
        $mpItem = new MercadoPago\Item();
        $mpItem->title = sprintf('Pedido #%s', $data->getNumber()); // what seller can see in operation/activity detail at mercadopago.com.ar/activities/detail/
        $mpItem->description = $data->getRestaurant()->getName(); // what user/buyer can see when is paying in MP's screens
        $mpItem->quantity = 1;
        $mpItem->unit_price = ($data->getTotal() / 100);
        $items[] = $mpItem;
        $preference->items = $items;

        // Add payer info to improve payment approvals
        // https://www.mercadopago.com.ar/developers/es/guides/online-payments/checkout-pro/advanced-integration
        $payer = new MercadoPago\Payer();
        $payer->email = $data->getCustomer()->getEmail();
        $preference->payer = $payer;

        $preference->marketplace_fee = ($data->getFeeTotal() / 100);

        // https://www.mercadopago.com.ar/developers/es/guides/online-payments/checkout-pro/configurations#bookmark_activa_el_modo_binario
        $preference->binary_mode = true; // If binary mode is active, the paymant can be only approved or rejected (can not be in process or pending)

        $preference->statement_descriptor = $data->getRestaurant()->getName(); // what user/buyer can see on its credit card details

        $preference->save();

        $response = new MercadopagoPreferenceResponse($preference);

        return $response->id();
    }
}
