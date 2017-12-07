<?php

namespace AppBundle\Controller;

use AppBundle\Entity\Restaurant;
use AppBundle\Entity\Menu;
use AppBundle\Form\RestaurantMenuType;
use AppBundle\Form\MenuType;
use AppBundle\Form\RestaurantType;
use AppBundle\Utils\ValidationUtils;
use Doctrine\Common\Collections\ArrayCollection;
use Symfony\Component\Form\Extension\Core\Type\SubmitType;
use Symfony\Component\Form\FormError;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;
use Symfony\Component\Validator\ConstraintViolationList;
use Symfony\Component\Validator\Validation;

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

    private function getAdditionnalProperties()
    {
        $countryCode = $this->getParameter('country_iso');
        $additionalProperties = [];

        switch ($countryCode) {
            case 'fr':
                return ['siret'];
            default:
                break;
        }
    }

    protected function editRestaurantAction($id, Request $request, $layout, array $routes, $formClass = RestaurantType::class)
    {
        $repository = $this->getDoctrine()->getRepository('AppBundle:Restaurant');
        $em = $this->getDoctrine()->getManagerForClass('AppBundle:Restaurant');

        if (null === $id) {
            $restaurant = new Restaurant();
        } else {
            $restaurant = $repository->find($id);
            $this->checkAccess($restaurant);
        }

        $form = $this->createForm($formClass, $restaurant, [
            'additional_properties' => $this->getAdditionnalProperties(),
            'validation_groups' => ['activable'],
        ]);
        $form->add('submit', SubmitType::class, array('label' => 'Save'));

        $activationErrors = [];

        $form->handleRequest($request);
        if ($form->isSubmitted()) {

            if ($form->isValid()) {
                $restaurant = $form->getData();

                if ($id === null) {
                    $em->persist($restaurant);
                    $this->getUser()->addRestaurant($restaurant);
                }

                $em->flush();

                $this->addFlash(
                    'notice',
                    $this->get('translator')->trans('Your changes were saved.')
                );

                return $this->redirectToRoute($routes['success'], ['id' => $restaurant->getId()]);
            } else {
                $violations = new ConstraintViolationList();
                foreach ($form->getErrors(true) as $error) {
                    $violations->add($error->getCause());
                }
                $activationErrors = ValidationUtils::serializeValidationErrors($violations);
            }

        } else {
            $validator = $this->get('validator');
            $violations = $validator->validate($restaurant, null, ['activable']);
            $activationErrors = ValidationUtils::serializeValidationErrors($violations);
        }

        return [
            'restaurant' => $restaurant,
            'activationErrors' => $activationErrors,
            'form' => $form->createView(),
            'layout' => $layout,
            'menu_route' => $routes['menu'],
            'orders_route' => $routes['orders'],
            'restaurants_route' => $routes['restaurants'],
            'google_api_key' => $this->getParameter('google_api_key'),
        ];
    }

    private function createMenuForm(Menu $menu, Menu\MenuSection $sectionAdded = null)
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
            ->getRepository(Restaurant::class)
            ->find($id);

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

        $originalModifiers = new ArrayCollection();
        foreach ($menu->getAllModifiers() as $modifier) {
            $originalModifiers->add($modifier);
        }

        $form = $this->createMenuForm($menu);

        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $menu = $form->getData();

            if ($addMenuSection) {
                $sectionAdded = new Menu\MenuSection();
                $sectionAdded->setName($form->get('addSection')->getData());

                $menu->addSection($sectionAdded);

            } else {

                // Make sure objects are mapped
                foreach ($menu->getSections() as $section) {
                    foreach ($section->getItems() as $item) {
                        if (null === $item->getSection()) {
                            $item->setSection($section);
                        }
                        foreach ($item->getModifiers() as $modifier) {
                            if (null === $modifier->getMenuItem()) {
                                $modifier->setMenuItem($item);
                            }
                            foreach ($modifier->getModifierChoices() as $modifierChoice) {
                                $modifierChoice->setMenuItemModifier($modifier);
                            }
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

                foreach ($originalModifiers as $originalModifier) {
                    if (false === $menu->getAllModifiers()->contains($originalModifier)) {
                        // TODO Soft delete modifier items
                        $originalModifier->setMenuItem(null);
                    } else {
                        foreach ($menu->getAllModifiers() as $updatedModifier) {
                            if ($updatedModifier === $originalModifier) {
                                // TODO Manage delete items
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

            if ($addMenuSection) {
                $em->refresh($menu);
                $form = $this->createMenuForm($menu, $sectionAdded);
            } else {
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
            'add_section_route' => $routes['add_section'],
        ];
    }
}
