<?php

namespace AppBundle\Api\Dto;

use AppBundle\Entity\LocalBusiness;
use Symfony\Component\Serializer\Annotation\Groups;

final class ShopCollectionInput
{
    /**
     * @var string
     */
    public $title;

    /**
     * @var LocalBusiness[]
     */
    public $shops;
}

