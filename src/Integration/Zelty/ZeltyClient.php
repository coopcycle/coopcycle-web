<?php

namespace AppBundle\Integration\Zelty;

use Sylius\Component\Order\Model\OrderInterface;
use Symfony\Contracts\HttpClient\HttpClientInterface;

class ZeltyClient {

    public function __construct(
        private HttpClientInterface $zeltyClient
    )
    { }

    public function setAuth(string $token): void
    {
        $this->zeltyClient = $this->zeltyClient->withOptions([
            'auth_bearer' => $token
        ]);
    }

    public function pushToZelty(OrderInterface $order): void {
        $this->zeltyClient->request("POST", "orders", [
            'body' => <<<JSON
            {
                "remote_id": $order->getId(),
                "display_id": $order->getNumber(),
                "fulfillment_type": "deliver_by_partner",
                "due_date": "ESTIMATED PICKUP TIME",
                "source": "WEB|MOBILE",
                "mode": "delivery",
                "customer": null,
                "address": {

                }
            }
            JSON
        ]);
    }

    public function upsertWebhook(string $event, ?string $url, string $secret_key): void
    {
        if (is_null($url)) {
            $this->zeltyClient->request("POST", "webhooks", [
                'body' => <<<JSON
                {
                    "webhooks": {
                      "$event": null
                   }
                }
                JSON
            ]);
            return;
        }

        $this->zeltyClient->request("POST", "webhooks", [
            'body' => <<<JSON
            {
                "webhooks": {
                  "$event": {
                     "target": "$url",
                     "version": "v2"
                  },
               },
               "secret_key": "$secret_key"
            }
            JSON
        ]);
    }

    // Get catalog taxes, return the parsed json
    public function getTaxes(): array {
        $req = $this->zeltyClient->request("GET", "catalog/taxes");
        return json_decode($req->getContent(), true);
    }


}
