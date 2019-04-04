<?php

namespace AppBundle\Controller;

use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Annotation\Route;

/**
 * @Route("/{_locale}", requirements={ "_locale": "%locale_regex%" })
 */
class LegalController extends AbstractController
{
    /**
     * @Route("/legal", name="legal")
     */
    public function indexAction()
    {
        $text = file_get_contents('http://coopcycle.org/terms/fr.md');

        return $this->render('@App/legal/index.html.twig', [
            'text' => $text
        ]);
    }
}
