<?php

namespace AppBundle\Controller;

use AppBundle\Entity\Restaurant;
use AppBundle\Entity\Menu;
use AppBundle\Entity\MenuSection;
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
            'orders_route' => $routes['orders'],
            'restaurants_route' => $routes['restaurants'],
            'google_api_key' => $this->getParameter('google_api_key'),
        ];
    }

    private function createMenuForm(Menu $menu, MenuSection $sectionAdded = null)
    {
        $form = $this->createForm(MenuType::class, $menu, [
            'section_added' => $sectionAdded,
        ]);
        $form->add('submit', SubmitType::class, array('label' => 'Save'));

        return $form;
    }

    private function removeSoftDeletedItems(Menu\MenuSection $section)
    {
        $em = $this->getDoctrine()->getManagerForClass(Menu\MenuItem::class);

        // Disable SoftDeleteable behavior to retrieve all items
        $em->getFilters()->disable('soft_deleteable');

        // FIXME
        // MenuSection::getItems does not return soft deleted items
        $items = $this->getDoctrine()
            ->getRepository(Menu\MenuItem::class)
            ->findBy(['section' => $section]);

        foreach ($items as $item) {
            $section->getItems()->removeElement($item);
            $item->setSection(null);
        }

        $em->getFilters()->enable('soft_deleteable');
    }

    protected function editMenuAction($id, Request $request, $layout, array $routes)
    {
        $em = $this->getDoctrine()->getManagerForClass(Restaurant::class);
        $addMenuSection = $request->attributes->get('_add_menu_section', false);
        $sectionAdded = null;

        $restaurant = $this->getDoctrine()
            ->getRepository(Restaurant::class)->find($id);

        $this->checkAccess($restaurant);

        $menu = $restaurant->getMenu();

        $originalSections = new ArrayCollection();
        foreach ($menu->getSections() as $section) {
            $originalSections->add($section);
        }

        $originalItems = new \SplObjectStorage();
        foreach ($menu->getSections() as $section) {
            $items = new ArrayCollection();
            foreach ($section->getItems() as $item) {
                $items->add($item);
            }

            $originalItems[$section] = $items;
        }

        $form = $this->createMenuForm($menu);

        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $menu = $form->getData();

            if ($addMenuSection) {
                $sectionAdded = $form->get('addSection')->getData();
                $menu->addSection($sectionAdded);

                $form = $this->createMenuForm($menu, $sectionAdded);
            } else {

                // Make sure sections & items are mapped
                foreach ($menu->getSections() as $section) {
                    foreach ($section->getItems() as $item) {
                        if (null === $item->getSection()) {
                            $item->setSection($section);
                        }
                    }
                }

                foreach ($originalSections as $originalSection) {

                    // Remove deleted sections
                    // Remove mapping between section & items
                    if (false === $menu->getSections()->contains($originalSection)) {

                        // First, soft delete items
                        foreach ($originalSection->getItems() as $item) {
                            // Don't remove the item to keep association with OrderItem
                            $originalSection->getItems()->removeElement($item);
                            $item->setSection(null);
                            $em->remove($item);
                        }

                        // Then, remove association for soft deleted items
                        $this->removeSoftDeletedItems($originalSection);

                        $originalSection->setMenu(null);
                        $em->remove($originalSection);

                    } else {

                        // Remove mapping between section & deleted item
                        foreach ($menu->getSections() as $updatedSection) {
                            if ($updatedSection === $originalSection) {
                                foreach ($originalItems[$originalSection] as $originalItem) {
                                    if (false === $updatedSection->getItems()->contains($originalItem)) {
                                        $originalSection->getItems()->removeElement($originalItem);
                                        $originalItem->setSection(null);
                                        $em->remove($originalItem);
                                    }
                                }
                            }
                        }
                    }
                }
            }

            $restaurant->setMenu($menu);

            $em->flush();

            $this->addFlash(
                'notice',
                $this->get('translator')->trans('Your changes were saved.')
            );

            if (!$addMenuSection) {
                return $this->redirectToRoute($routes['success'], ['id' => $restaurant->getId()]);
            }
        }

        return [
            'restaurant' => $restaurant,
            'form' => $form->createView(),
            'layout' => $layout,
            'section_added' => $sectionAdded,
            'restaurants_route' => $routes['restaurants'],
            'restaurant_route' => $routes['restaurant'],
        ];
    }
}
