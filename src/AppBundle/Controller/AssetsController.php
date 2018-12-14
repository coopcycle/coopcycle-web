<?php

namespace AppBundle\Controller;

use Symfony\Bundle\FrameworkBundle\Controller\Controller;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Annotation\Route;

class AssetsController extends Controller
{
    /**
     * @Route("/js/data", name="js_data")
     */
    public function jsDataAction()
    {
        return $this->render('@App/_partials/app.js.twig', [
            'currency_context' => $this->get('sylius.context.currency'),
            'country_iso' => $this->getParameter('country_iso'),
        ]);
    }
}
