<?php

namespace AppBundle\Entity;

class Shop extends Restaurant
{
    public function __construct()
    {
        parent::__construct();

        $this->type = 'shop';
    }
}
