<?php

namespace AppBundle\Api\Dto;

use AppBundle\Entity\Sylius\Product;
use Symfony\Component\Serializer\Annotation\Groups;

final class MenuInput
{
    #[Groups(["restaurant_menus", "restaurant_menu"])]
    public $name;

    #[Groups(["restaurant_menus", "restaurant_menu"])]
    public $description;

    /**
     * @var Product[]|null Null when the payload does not contain the key,
     *                     meaning the products must be left untouched.
     */
    #[Groups(["restaurant_menu"])]
    public ?array $products = null;

    #[Groups(["restaurant_menus"])]
    public $sections = [];
}
