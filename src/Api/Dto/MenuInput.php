<?php

namespace AppBundle\Api\Dto;

use AppBundle\Entity\Sylius\Product;
use Symfony\Component\Serializer\Annotation\Groups;

final class MenuInput
{
    #[Groups(["restaurant_menus", "restaurant_menu"])]
    public $name;

    /**
     * @var Product[]
     */
    #[Groups(["restaurant_menu"])]
    public $products = [];

    #[Groups(["restaurant_menus"])]
    public $sections = [];
}
