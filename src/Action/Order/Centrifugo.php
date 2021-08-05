<?php

namespace AppBundle\Action\Order;

use AppBundle\Api\Resource\Centrifugo as CentrifugoResponse;
use phpcent\Client as CentrifugoClient;

class Centrifugo
{
    private $centrifugoClient;
    private $centrifugoNamespace;

    public function __construct(CentrifugoClient $centrifugoClient, string $centrifugoNamespace)
    {
        $this->centrifugoClient = $centrifugoClient;
        $this->centrifugoNamespace = $centrifugoNamespace;
    }

    public function __invoke($data)
    {
        $exp = clone $data->getShippingTimeRange()->getUpper();
        $exp->modify('+3 hours');

        $output = new CentrifugoResponse();

        $output->token = $this->centrifugoClient->generateConnectionToken($data->getId(), $exp->getTimestamp());
        $output->namespace = $this->centrifugoNamespace;
        $output->channel = sprintf('%s_order_events#%d', $this->centrifugoNamespace, $data->getId());

        return $output;
    }
}
