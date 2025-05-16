<?php

namespace AppBundle\Api\Dto;

use AppBundle\Entity\Address;
use DateTime;
use Symfony\Component\Serializer\Annotation\Groups;

//Try to merge into TaskInput (as a part of DeliveryInput)?
final class DeliveryFormTaskOutput
{

    #[Groups(['delivery'])]
    public int|null $id = null;

    #[Groups(['delivery'])]
    public string|null $type = null;

    #[Groups(['delivery'])]
    public Address|null $address = null;

    #[Groups(['delivery'])]
    public DateTime|null $after = null;

    #[Groups(['delivery'])]
    public DateTime|null $before = null;

    #[Groups(['delivery'])]
    public string|null $comments = null;

    /**
     * @var string[]
     */
    #[Groups(['delivery'])]
    public array|null $tags = null;

    #[Groups(['delivery'])]
    public int|null $weight = null;

    /**
     * @var DeliveryFormTaskPackageDto[]|null
     */
    #[Groups(['delivery'])]
    public array|null $packages = null;

    #[Groups(['delivery'])]
    public array|null $metadata = null;

}
