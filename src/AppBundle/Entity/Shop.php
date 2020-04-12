<?php

namespace AppBundle\Entity;

/**
 *
 */
class Shop extends LocalBusiness
{
    public function __construct()
    {
        $this->type = 'shop';

        parent::__construct();
    }
}
