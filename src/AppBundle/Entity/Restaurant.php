<?php

namespace AppBundle\Entity;

/**
 *
 */
class Restaurant extends LocalBusiness
{
    public function __construct()
    {
        $this->type = 'restaurant';

        parent::__construct();
    }
}
