<?php

namespace AppBundle\Controller;

use ReflectionObject;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\Serializer\Normalizer\DenormalizerInterface;

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
    public function componentAction($id, Request $request, DenormalizerInterface $denormalizer)
    {
        if (array_key_exists($id, $this->mapping)) {
            $template = $this->mapping[$id];
        } else {
            // $id is the path to the Twig template in the storybook/ directory
            // Args are read from the query parameters and sent to the template
            $template = sprintf('storybook/%s.html.twig', $id);
        }

        $args = $request->query->all();

        $args = array_map(function ($value) use ($denormalizer) {

            if (str_starts_with($value, '[')) {
                //TODO; deserialize objects inside an array similarly to the individual objects below
                return json_decode($value, true);
            }

            if (str_starts_with($value, '{')) {
                $data = json_decode($value, true);
                if (array_key_exists('resource_class', $data)) {
                    $resourceClass = $data['resource_class'];

                    $obj = $denormalizer->denormalize($data, $resourceClass, 'jsonld');

                    if (array_key_exists('id', $data)) {
                        $objId = $obj->getId();

                        // the id could be not set when denormalizing, so we set it manually
                        // we need to do this via reflection in case the id property is private
                        if (null === $objId) {
                            $id = $data['id'];

                            $refObject = new ReflectionObject($obj);
                            $refProperty = $refObject->getProperty('id');
                            $refProperty->setAccessible(true);
                            $refProperty->setValue($obj, $id);
                        }
                    }

                    return $obj;
                } else {
                    return $data;
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
