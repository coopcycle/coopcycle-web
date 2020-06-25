<?php

namespace AppBundle\LoopEat;

use AppBundle\Entity\ApiUser;
use GuzzleHttp\Exception\RequestException;

class Context
{
    private $loopeatBalance = 0;
    private $loopeatCredit = 0;

    public function __construct(Client $client)
    {
        $this->client = $client;
    }

    public function initialize(ApiUser $customer)
    {
        if ($customer->hasLoopEatCredentials()) {

            try {

                $loopeatCustomer = $this->client->currentCustomer($customer);

                $this->loopeatBalance = $loopeatCustomer['loopeatBalance'];
                $this->loopeatCredit = $loopeatCustomer['loopeatCredit'];

            } catch (RequestException $e) {
                // TODO Log error
            }
            // $missingLoopeat = $packagingQuantity - $availableLoopeat - $pledgeReturn;
        }
    }

    public function getLoopeatBalance()
    {
        return $this->loopeatBalance;
    }

    public function getLoopeatCredit()
    {
        return $this->loopeatCredit;
    }
}
