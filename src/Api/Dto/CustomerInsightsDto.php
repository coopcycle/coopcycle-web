<?php

namespace AppBundle\Api\Dto;

use AppBundle\Entity\LocalBusiness;
use Symfony\Component\Serializer\Annotation\Groups;

final class CustomerInsightsDto
{
    #[Groups(["customer"])]
    public int $averageOrderTotal = 0;

    #[Groups(["customer"])]
    public ?\DateTimeInterface $firstOrderedAt;

    #[Groups(["customer"])]
    public ?\DateTimeInterface $lastOrderedAt;

    #[Groups(["customer"])]
    public int $numberOfOrders = 0;

    #[Groups(["customer"])]
    public ?LocalBusiness $favoriteRestaurant;
}
