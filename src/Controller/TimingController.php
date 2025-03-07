<?php

namespace AppBundle\Controller;

use AppBundle\Service\TimingRegistry;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\Routing\Annotation\Route;

class TimingController extends AbstractController
{
    /**
     * @Route("/restaurant/{id}/timing", name="restaurant_fulfillment_timing", methods={"GET"})
     */
    public function fulfillmentTimingAction($id,
        TimingRegistry $timingRegistry)
    {
        return new JsonResponse(
            $timingRegistry->getAllFulfilmentMethodsForId($id)
        );
    }
}
