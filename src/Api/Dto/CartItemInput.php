<?php

namespace AppBundle\Api\Dto;

use Symfony\Component\Serializer\Annotation\Groups;

final class CartItemInput
{
    /**
     * @Groups({"cart"})
     */
    public $product;

    /**
     * @Groups({"cart"})
     */
    public $quantity;

    /**
     * @Groups({"cart"})
     */
    public $options = [];
}
