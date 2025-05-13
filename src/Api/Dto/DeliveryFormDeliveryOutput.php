<?php

namespace AppBundle\Api\Dto;

use AppBundle\Entity\Sylius\ArbitraryPrice;
use Symfony\Component\Serializer\Annotation\Groups;

//Try to merge into DeliveryInput?
final class DeliveryFormDeliveryOutput
{
    #[Groups(['delivery'])]
    public int|null $id = null;

    /**
     * @var DeliveryFormTaskOutput[]|null
     */
    #[Groups(['delivery'])]
    public array|null $tasks = null;

    #[Groups(['delivery'])]
    public ArbitraryPrice|null $arbitraryPrice = null;

    #[Groups(['delivery'])]
    public string|null $trackingUrl = null;
}
