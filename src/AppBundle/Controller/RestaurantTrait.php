<?php

namespace AppBundle\Controller;

use AppBundle\Entity\Restaurant;
use AppBundle\Form\RestaurantMenuType;
use AppBundle\Form\RestaurantType;
use Symfony\Component\Form\Extension\Core\Type\SubmitType;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;

trait RestaurantTrait
{
    protected function checkAccess(Restaurant $restaurant)
    {
        if ($this->getUser()->hasRole('ROLE_ADMIN')) {
            return;
        }

        if ($this->getUser()->ownsRestaurant($restaurant)) {
            return;
        }

        throw new AccessDeniedHttpException();
    }

    protected function editRestaurantAction($id, Request $request, $layout, array $routes)
    {
        $repository = $this->getDoctrine()->getRepository('AppBundle:Restaurant');
        $em = $this->getDoctrine()->getManagerForClass('AppBundle:Restaurant');

        if (null === $id) {
            $restaurant = new Restaurant();
        } else {
            $restaurant = $repository->find($id);
            $this->checkAccess($restaurant);
        }

        $form = $this->createForm(RestaurantType::class, $restaurant);
        $form->add('submit', SubmitType::class, array('label' => 'Save'));

        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {

            $restaurant = $form->getData();

            if ($id === null) {
                $em->persist($restaurant);
                $this->getUser()->addRestaurant($restaurant);
            }

            $em->flush();

            return $this->redirectToRoute($routes['success']);
        }

        return [
            'restaurant' => $restaurant,
            'form' => $form->createView(),
            'layout' => $layout,
            'menu_route' => $routes['menu'],
            'restaurants_route' => $routes['restaurants'],
            'google_api_key' => $this->getParameter('google_api_key'),
        ];
    }

    protected function editMenuAction($id, Request $request, $layout, array $routes)
    {
        $restaurant = $this->getDoctrine()
            ->getRepository('AppBundle:Restaurant')->find($id);

        $this->checkAccess($restaurant);

        $form = $this->createForm(RestaurantMenuType::class, $restaurant);
        $form->add('submit', SubmitType::class, array('label' => 'Save'));

        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {

            $restaurant = $form->getData();

            $em = $this->getDoctrine()->getManagerForClass('AppBundle:Restaurant');
            $em->flush();

            return $this->redirectToRoute($routes['success']);
        }

        $categories = [
            'Entrées',
            'Plats',
            'Desserts',
        ];

        return [
            'restaurant' => $restaurant,
            'categories' => $categories,
            'form' => $form->createView(),
            'layout' => $layout,
            'restaurants_route' => $routes['restaurants'],
            'restaurant_route' => $routes['restaurant'],
        ];
    }
}