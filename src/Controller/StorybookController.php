<?php

namespace AppBundle\Controller;

use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\Serializer\SerializerInterface;

class StorybookController extends AbstractController
{
    private $mapping = [
        'restaurant_card' => 'components/restaurant/restaurant_card/restaurant_card.html.twig',
        'restaurant_badge' => 'components/restaurant/restaurant_badge/restaurant_badge.html.twig',
        'restaurant_tag' => 'components/restaurant/restaurant_tag/restaurant_tag.html.twig',
        'product' => 'components/restaurant/product/menu_item.html.twig',
        'product_badge' => 'components/restaurant/product_badge/product_badge.html.twig',
    ];

	/**
     * @Route("/storybook/component/{id}", name="storybook_component")
     */
    public function componentAction($id, Request $request, SerializerInterface $serializer)
    {
        if (array_key_exists($id, $this->mapping)) {
            $template = $this->mapping[$id];
        } else {
            // $id is the path to the Twig template in the storybook/ directory
            // Args are read from the query parameters and sent to the template
            $template = sprintf('storybook/%s.html.twig', $id);
        }

        $args = $request->query->all();

        $args = array_map(function ($value) use ($serializer) {

            if (str_starts_with($value, '[')) {
                //TODO; deserialize objects inside an array similarly to the individual objects below
                return json_decode($value, true);
            }

            if (str_starts_with($value, '{')) {
                $arr = json_decode($value, true);
                if (array_key_exists('className', $arr)) {
                    return $serializer->deserialize($value, $arr['className'], 'jsonld');
                } else {
                    return $arr;
                }
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
