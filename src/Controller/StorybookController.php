<?php

namespace AppBundle\Controller;

use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;

class StorybookController extends AbstractController
{
	/**
     * @Route("/storybook/component/{id}", name="storybook_component")
     */
    public function componentAction($id, Request $request)
    {
        // $id is the path to the Twig template in the storybook/ directory
        // Args are read from the query parameters and sent to the template
        $template = sprintf('storybook/%s.html.twig', $id);
        $context = ['args' => $request->query->all(), 'id' => $id];
        // $content = $this->render($template, $context);

        // // During development, storybook is served from a different port than the Symfony app
        // // You can use nelmio/cors-bundle to set the Access-Control-Allow-Origin header correctly
        // $headers = ['Access-Control-Allow-Origin' => 'http://localhost:6006'];

        // return new Response($content, Response::HTTP_OK, $headers);

        return $this->render($template, $context);
    }
}
