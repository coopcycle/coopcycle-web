<?php

namespace AppBundle\Controller;

use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

/**
 * Controller for test pages and debugging utilities
 */
class TestController extends AbstractController
{
    /**
     * Test page that will always crash on React render
     * Useful for testing error boundaries and error handling
     */
    #[Route(path: '/test/crash', name: 'test_crash_page')]
    public function crashTestAction(): Response
    {
        return $this->render('test/crash.html.twig');
    }
}
