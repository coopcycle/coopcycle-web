<?php

namespace AppBundle\Action\Restaurant;

use Symfony\Component\HttpFoundation\Request;

class Menu
{
    public function __invoke($data, Request $request)
    {
        $restaurant = $data;

        // TODO Throw 404 when there is no menu

        return $restaurant->getMenuTaxon();
    }
}
