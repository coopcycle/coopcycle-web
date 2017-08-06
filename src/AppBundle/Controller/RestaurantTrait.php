<?php

namespace AppBundle\Controller;

use AppBundle\Entity\Restaurant;
use AppBundle\Entity\Menu;
use AppBundle\Form\RestaurantMenuType;
use AppBundle\Form\MenuType;
use AppBundle\Form\RestaurantType;
use Doctrine\Common\Collections\ArrayCollection;
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

    private function createMenuForm(Menu $menu)
    {
        $form = $this->createForm(MenuType::class, $menu);
        $form->add('submit', SubmitType::class, array('label' => 'Save'));

        return $form;
    }

    protected function editMenuAction($id, Request $request, $layout, array $routes)
    {
        $em = $this->getDoctrine()->getManagerForClass('AppBundle:Restaurant');
        $addMenuSection = $request->attributes->get('_add_menu_section', false);

        $restaurant = $this->getDoctrine()
            ->getRepository('AppBundle:Restaurant')->find($id);

        $this->checkAccess($restaurant);

        $menu = $restaurant->getMenu();

        $originalSections = new ArrayCollection();
        foreach ($menu->getSections() as $section) {
            $originalSections->add($section);
        }

        $form = $this->createMenuForm($menu);

        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {

            $menu = $form->getData();

            if ($addMenuSection) {
                $section = $form->get('addSection')->getData();
                $menu->addSection($section);

                $form = $this->createMenuForm($menu);
            } else {
                // Properly remove items from deleted sections
                foreach ($originalSections as $section) {
                    if (false === $menu->getSections()->contains($section)) {
                        foreach ($section->getItems() as $item) {
                            $section->getItems()->removeElement($item);
                            $em->remove($item);
                        }
                    }
                }
            }

            $restaurant->setMenu($menu);

            $em->flush();

            if (!$addMenuSection) {
                return $this->redirectToRoute($routes['success']);
            }
        }

        return [
            'restaurant' => $restaurant,
            'form' => $form->createView(),
            'layout' => $layout,
            'restaurants_route' => $routes['restaurants'],
            'restaurant_route' => $routes['restaurant'],
        ];
    }
}
