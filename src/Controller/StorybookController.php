<?php

namespace AppBundle\Controller;

use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;

class StorybookController extends AbstractController
{
    private $mapping = [
        'restaurant-card' => 'components/restaurant_card/restaurant_card.html.twig',
        'tag' => 'components/restaurant_card/tag/tag.html.twig',
        'badge' => 'components/restaurant_card/badge/badge.html.twig'
    ];

	/**
     * @Route("/storybook/component/{id}", name="storybook_component")
     */
    public function componentAction($id, Request $request)
    {
        if (array_key_exists($id, $this->mapping)) {
            $template = $this->mapping[$id];
        } else {
            // $id is the path to the Twig template in the storybook/ directory
            // Args are read from the query parameters and sent to the template
            $template = sprintf('storybook/%s.html.twig', $id);
        }

        $args = $request->query->all();

        $args = array_map(function ($value) {

            if (str_starts_with($value, '[')) {
                return json_decode($value, true);
            }

            if (false !== strpos($value, ',')) {
                return explode(',', $value);
            }

            switch ($value) {
                case 'true':
                    return true;
                case 'false':
                    return false;
            }

            return $value;

        }, $args);

        return $this->render($template, $args);
    }
}
