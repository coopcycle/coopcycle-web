<?php

namespace AppBundle\Action\Restaurant;

use AppBundle\Entity\Restaurant;
use Sensio\Bundle\FrameworkExtraBundle\Configuration\Method;
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
     *   }
     * )
     * @Method("GET")
     */
    public function __invoke(Restaurant $data, Request $request)
    {
        $restaurant = $data;

        return $restaurant->getMenuTaxon();
    }
}
