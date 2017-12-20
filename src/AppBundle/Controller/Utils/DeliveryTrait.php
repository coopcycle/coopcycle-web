<?php

namespace AppBundle\Controller\Utils;

use AppBundle\Entity\Delivery;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;

trait DeliveryTrait
{
    /**
     * @return array
     */
    abstract protected function getDeliveryRoutes();

    public function pickDeliveryAction($id, Request $request)
    {
        $delivery = $this->getDoctrine()->getRepository(Delivery::class)->find($id);
        $this->accessControl($delivery);

        $delivery->setStatus(Delivery::STATUS_PICKED);
        $this->getDoctrine()->getManagerForClass(Delivery::class)->flush();

        $routes = $this->getDeliveryRoutes();

        return $this->redirectToRoute($routes['list']);
    }

    public function deliverDeliveryAction($id, Delivery $delivery)
    {
        $delivery = $this->getDoctrine()->getRepository(Delivery::class)->find($id);
        $this->accessControl($delivery);

        $delivery->setStatus(Delivery::STATUS_DELIVERED);
        $this->getDoctrine()->getManagerForClass(Delivery::class)->flush();

        $routes = $this->getDeliveryRoutes();

        return $this->redirectToRoute($routes['list']);
    }
}
