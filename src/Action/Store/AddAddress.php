<?php

namespace AppBundle\Action\Store;

use AppBundle\Entity\Store;


class AddAddress
{
    public function __invoke(Store $data)
    {
        // TODO : we return the store instance but it maybe better to return the created address. to investigate : how to retreve the created address instance.
        return $data;
    }
}
