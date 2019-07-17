<?php

namespace AppBundle\Controller;

use Sylius\Component\Currency\Context\CurrencyContextInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;

class AssetsController extends AbstractController
{
    /**
     * @Route("/js/data", name="js_data")
     */
    public function jsDataAction(CurrencyContextInterface $currencyContext)
    {
        $response = new Response();

        $response->headers->set('Content-Type', 'text/javascript');

        return $this->render('@App/_partials/app.js.twig', [
            'currency_context' => $currencyContext,
            'country_iso' => $this->getParameter('country_iso'),
        ], $response);
    }
}
