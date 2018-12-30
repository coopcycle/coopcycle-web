<?php

namespace AppBundle\Controller;

use Sylius\Component\Currency\Context\CurrencyContextInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Annotation\Route;

class AssetsController extends AbstractController
{
    /**
     * @Route("/js/data", name="js_data")
     */
    public function jsDataAction(CurrencyContextInterface $currencyContext)
    {
        return $this->render('@App/_partials/app.js.twig', [
            'currency_context' => $currencyContext,
            'country_iso' => $this->getParameter('country_iso'),
        ]);
    }
}
