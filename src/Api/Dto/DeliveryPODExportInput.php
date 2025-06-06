<?php

use AppBundle\Entity\Store;
use Symfony\Component\Validator\Constraints as Assert;

final class DeliveryPODExportInput
{

    #[Assert\NotNull]
    public Store $store;

    #[Assert\NotNull]
    public \DateTimeInterface $from;

    #[Assert\NotNull]
    public \DateTimeInterface $to;

}
