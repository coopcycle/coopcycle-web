<?php

namespace AppBundle\Action\Restaurant;

use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Annotation\Route;

class Menu
{
    /**
     * @Route(
     *   name="api_restaurant_menu",
     *   path="/restaurants/{id}/menu",
     *   defaults={
     *     "_api_resource_class"=Restaurant::class,
     *     "_api_item_operation_name"="restaurant_menu"
     *   },
     *   methods={"GET"}
     * )
     */
    public function __invoke($data, Request $request)
    {
        $restaurant = $data;

        return $restaurant->getMenuTaxon();
    }
}
