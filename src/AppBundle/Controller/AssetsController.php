<?php

namespace AppBundle\Controller;

// use Craue\ConfigBundle\Util\Config as CraueConfig;
use AppBundle\Service\SettingsManager;
use OzdemirBurak\Iris\Color\Hex;
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
        return $this->render('@App/_partials/app.js.twig', [
            'currency_context' => $currencyContext,
            'country_iso' => $this->getParameter('country_iso'),
        ]);
    }

    /**
     * @Route("/css/custom.css", name="custom_css")
     */
    public function customCssAction(Request $request, SettingsManager $settingsManager)
    {
        $primaryColor = $settingsManager->get('primary_color');

        if (!$primaryColor) {
            $primaryColor = '#f8f8f8';
        }

        $hex = new Hex($primaryColor);

        $response = new Response();
        $response->headers->set('Content-Type', 'text/css');

        return $this->render('@App/index/custom_css.css.twig', [
            'primary_color' => $primaryColor,
            'primary_color_darken' => $hex->darken(20),
            'primary_color_lighten' => $hex->lighten(20),
            'primary_color_is_dark' => $hex->isDark(),
            'primary_color_is_light' => $hex->isLight(),
        ], $response);
    }
}
