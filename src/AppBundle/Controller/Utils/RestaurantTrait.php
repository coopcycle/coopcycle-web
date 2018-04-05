<?php

namespace AppBundle\Controller\Utils;

use AppBundle\Entity\ClosingRule;
use AppBundle\Entity\Restaurant;
use AppBundle\Entity\Menu;
use AppBundle\Form\ClosingRuleType;
use AppBundle\Form\RestaurantMenuType;
use AppBundle\Form\MenuType;
use AppBundle\Form\RestaurantType;
use AppBundle\Utils\ValidationUtils;
use Doctrine\Common\Collections\ArrayCollection;
use Sylius\Component\Order\Model\OrderInterface;
use Symfony\Component\Form\Extension\Core\Type\SubmitType;
use Symfony\Component\Form\FormError;
use Symfony\Component\Form\FormInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;
use Symfony\Component\Validator\ConstraintViolationList;
use Symfony\Component\Validator\Validation;

trait RestaurantTrait
{
    abstract protected function getRestaurantList(Request $request);

    public function restaurantListAction(Request $request)
    {
        $routes = $request->attributes->get('routes');

        [ $restaurants, $pages, $page ] = $this->getRestaurantList($request);

        return $this->render($request->attributes->get('template'), [
            'layout' => $request->attributes->get('layout'),
            'restaurants' => $restaurants,
            'pages' => $pages,
            'page' => $page,
            'dashboard_route' => $routes['dashboard'],
            'menu_route' => $routes['menu'],
            'restaurant_route' => $routes['restaurant'],
        ]);
    }

    private function renderRestaurantForm(Restaurant $restaurant, Request $request)
    {
        $form = $this->createForm(RestaurantType::class, $restaurant, [
            'additional_properties' => $this->getLocalizedLocalBusinessProperties(),
            'validation_groups' => ['activable'],
        ]);

        $activationErrors = [];
        $routes = $request->attributes->get('routes');

        $form->handleRequest($request);
        if ($form->isSubmitted()) {

            if ($form->isValid()) {
                $restaurant = $form->getData();

                if ($restaurant->getId() === null) {
                    $this->getDoctrine()->getManagerForClass(Restaurant::class)->persist($restaurant);
                    // FIXME Do this only when user is not admin ?
                    $this->getUser()->addRestaurant($restaurant);
                }

                $this->getDoctrine()->getManagerForClass(Restaurant::class)->flush();

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

        return $this->render($request->attributes->get('template'), [
            'restaurant' => $restaurant,
            'activationErrors' => $activationErrors,
            'form' => $form->createView(),
            'layout' => $request->attributes->get('layout'),
            'menu_route' => $routes['menu'],
            'dashboard_route' => $routes['dashboard'],
            'planning_route' => $routes['planning'],
            'restaurants_route' => $routes['restaurants'],
        ]);
    }

    public function restaurantAction($id, Request $request)
    {
        $repository = $this->getDoctrine()->getRepository(Restaurant::class);

        $restaurant = $repository->find($id);

        $this->accessControl($restaurant);

        return $this->renderRestaurantForm($restaurant, $request);
    }

    public function newRestaurantAction(Request $request)
    {
        // TODO Check roles
        $restaurant = new Restaurant();

        return $this->renderRestaurantForm($restaurant, $request);
    }

    private function renderRestaurantDashboard(Request $request, Restaurant $restaurant, OrderInterface $order = null)
    {
        $this->accessControl($restaurant);

        $date = new \DateTime('now');
        if ($request->query->has('date')) {
            $date = new \DateTime($request->query->get('date'));
        }
        $date->modify('-1 day');

        $qb = $this->get('sylius.repository.order')
            ->createQueryBuilder('o')
            ->andWhere('o.restaurant = :restaurant')
            ->andWhere('DATE(o.shippedAt) >= :date')
            ->andWhere('o.state != :state')
            ->setParameter('restaurant', $restaurant)
            ->setParameter('date', $date)
            ->setParameter('state', OrderInterface::STATE_CART);
            ;

        $orders = $qb->getQuery()->getResult();

        $routes = $request->attributes->get('routes');

        return $this->render($request->attributes->get('template'), [
            'layout' => $request->attributes->get('layout'),
            'restaurant' => $restaurant,
            'restaurant_json' => $this->get('serializer')->serialize($restaurant, 'jsonld'),
            'orders' => $orders,
            'order' => $order,
            'orders_normalized' => $this->get('serializer')->normalize($orders, 'json', ['groups' => ['order']]),
            'order_normalized' => $this->get('serializer')->normalize($order, 'json', ['groups' => ['order']]),
            'restaurants_route' => $routes['restaurants'],
            'restaurant_route' => $routes['restaurant'],
            'routes' => $routes,
            'date' => $date,
        ]);
    }

    public function restaurantDashboardAction($restaurantId, Request $request)
    {
        $restaurant = $this->getDoctrine()
            ->getRepository(Restaurant::class)
            ->find($restaurantId);

        return $this->renderRestaurantDashboard($request, $restaurant);
    }

    public function restaurantDashboardOrderAction($restaurantId, $orderId, Request $request)
    {
        $restaurantRepository = $this->getDoctrine()->getRepository(Restaurant::class);

        $restaurant = $restaurantRepository->find($restaurantId);
        $order = $this->get('sylius.repository.order')->find($orderId);

        return $this->renderRestaurantDashboard($request, $restaurant, $order);
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

    public function restaurantMenuAction($id, Request $request)
    {
        $routes = $request->attributes->get('routes');

        $em = $this->getDoctrine()->getManagerForClass(Restaurant::class);
        $addMenuSection = $request->attributes->get('add_menu_section', false);
        $sectionAdded = null;

        $restaurant = $this->getDoctrine()
            ->getRepository(Restaurant::class)
            ->find($id);

        $this->accessControl($restaurant);

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

        return $this->render($request->attributes->get('template'), [
            'layout' => $request->attributes->get('layout'),
            'restaurant' => $restaurant,
            'form' => $form->createView(),
            'section_added' => $sectionAdded,
            'restaurants_route' => $routes['restaurants'],
            'restaurant_route' => $routes['restaurant'],
            'add_section_route' => $routes['add_section'],
        ]);
    }

    public function restaurantPlanningAction($id, Request $request) {

        $restaurant = $this->getDoctrine()
            ->getRepository(Restaurant::class)
            ->find($id);

        $form = $this->createForm(ClosingRuleType::class);
        $form->add('submit', SubmitType::class, array('label' => 'Save'));

        $form->handleRequest($request);

        $routes = $request->attributes->get('routes');

        if ($form->isSubmitted() && $form->isValid()) {
            $closingRule = $form->getData();
            $closingRule->setRestaurant($restaurant);
            $manager = $this->getDoctrine()->getManagerForClass(ClosingRule::class);
            $manager->persist($closingRule);
            $manager->flush();
            $this->addFlash(
                'notice',
                $this->get('translator')->trans('Your changes were saved.')
            );
            return $this->redirectToRoute($routes['success'], ['id' => $restaurant->getId()]);
        }

        return $this->render($request->attributes->get('template'), [
            'layout' => $request->attributes->get('layout'),
            'closing_rules_json' => $this->get('serializer')->serialize($restaurant->getClosingRules(), 'json', ['groups' => ['planning']]),
            'opening_hours_json' => json_encode($restaurant->getOpeningHours()),
            'restaurant' => $restaurant,
            'restaurants_route' => $routes['restaurants'],
            'restaurant_route' => $routes['restaurant'],
            'routes' => $routes,
            'form' => $form->createView()
        ]);
    }
}
