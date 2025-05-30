<?php

namespace AppBundle\Api\Dto;

use Symfony\Component\Serializer\Annotation\Groups;

//Try to merge into DeliveryInput?
final class DeliveryFormDeliveryOutput
{
    #[Groups(['delivery'])]
    public int|null $id = null;

    #[Groups(['delivery'])]
    public string|null $order = null;

    /**
     * @var DeliveryFormTaskOutput[]|null
     */
    #[Groups(['delivery'])]
    public array|null $tasks = null;

    #[Groups(['delivery'])]
    public ArbitraryPriceDto|null $arbitraryPrice = null;

    #[Groups(['delivery'])]
    public bool $isSavedOrder = false;

    #[Groups(['delivery'])]
    public string|null $trackingUrl = null;
}
