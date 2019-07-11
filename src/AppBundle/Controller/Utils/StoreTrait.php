<?php

namespace AppBundle\Controller\Utils;

use AppBundle\Entity\Address;
use AppBundle\Entity\ApiUser;
use AppBundle\Entity\Delivery;
use AppBundle\Entity\Store;
use AppBundle\Exception\Pricing\NoRuleMatchedException;
use AppBundle\Form\AddUserType;
use AppBundle\Form\StoreType;
use AppBundle\Form\AddressType;
use AppBundle\Service\DeliveryManager;
use AppBundle\Service\OrderManager;
use Symfony\Component\Form\FormError;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;

trait StoreTrait
{
    abstract protected function getStoreList(Request $request);

    public function storeListAction(Request $request)
    {
        [ $stores, $pages, $page ] = $this->getStoreList($request);

        $routes = $request->attributes->get('routes');

        return $this->render($request->attributes->get('template'), [
            'layout' => $request->attributes->get('layout'),
            'stores' => $stores,
            'pages' => $pages,
            'page' => $page,
            'store_route' => $routes['store'],
            'store_delivery_new_route' => $routes['store_delivery_new'],
            'store_deliveries_route' => $routes['store_deliveries'],
        ]);
    }

    public function storeUsersAction($id, Request $request)
    {
        $store = $this->getDoctrine()->getRepository(Store::class)->find($id);

        $this->accessControl($store);

        $addUserForm = $this->createForm(AddUserType::class);

        $routes = $request->attributes->get('routes');

        $addUserForm->handleRequest($request);
        if ($addUserForm->isSubmitted() && $addUserForm->isValid()) {

            $user = $addUserForm->get('user')->getData();

            // FIXME Association should be inversed
            $user->addStore($store);

            $this->getDoctrine()->getManagerForClass(ApiUser::class)->flush();

            return $this->redirectToRoute('admin_store_users', ['id' => $id]);
        }

        return $this->render('@App/store/users.html.twig', [
            'layout' => $request->attributes->get('layout'),
            'store' => $store,
            'users' => $store->getOwners(),
            'stores_route' => $routes['stores'],
            'store_route' => $routes['store'],
            'add_user_form' => $addUserForm->createView(),
        ]);
    }

    public function storeAddressesAction($id, Request $request)
    {
        $store = $this->getDoctrine()->getRepository(Store::class)->find($id);

        $this->accessControl($store);

        $routes = $request->attributes->get('routes');

        return $this->render('@App/store/addresses.html.twig', [
            'layout' => $request->attributes->get('layout'),
            'store' => $store,
            'stores_route' => $routes['stores'],
            'store_route' => $routes['store'],
        ]);
    }

    public function storeAddressAction($storeId, $addressId, Request $request)
    {
        $store = $this->getDoctrine()->getRepository(Store::class)->find($storeId);

        $this->accessControl($store);

        $address = $this->getDoctrine()->getRepository(Address::class)->find($addressId);

        if (!$store->getAddresses()->contains($address)) {
            throw new AccessDeniedHttpException('Access denied');
        }

        $routes = $request->attributes->get('routes');

        $form = $this->createForm(AddressType::class, $address, [
            'with_name' => true
        ]);

        $form->handleRequest($request);
        if ($form->isSubmitted() && $form->isValid()) {

            $address = $form->getData();

            $this->getDoctrine()->getManagerForClass(Store::class)->flush();

            $this->addFlash(
                'notice',
                $this->get('translator')->trans('global.changesSaved')
            );

            return $this->redirectToRoute('admin_store', ['id' => $store->getId()]);
        }

        return $this->render('@App/store/new_address.html.twig', [
            'layout' => $request->attributes->get('layout'),
            'store' => $store,
            'stores_route' => $routes['stores'],
            'store_route' => $routes['store'],
            'form' => $form->createView(),
        ]);
    }

    public function newStoreAddressAction($id, Request $request)
    {
        $store = $this->getDoctrine()->getRepository(Store::class)->find($id);

        $this->accessControl($store);

        $address = new Address();

        $routes = $request->attributes->get('routes');

        $form = $this->createForm(AddressType::class, $address, [
            'with_name' => true
        ]);

        $form->handleRequest($request);
        if ($form->isSubmitted() && $form->isValid()) {

            $address = $form->getData();

            $store->addAddress($address);

            $this->getDoctrine()->getManagerForClass(Store::class)->flush();

            $this->addFlash(
                'notice',
                $this->get('translator')->trans('global.changesSaved')
            );

            return $this->redirectToRoute('admin_store', ['id' => $id]);
        }

        return $this->render('@App/store/new_address.html.twig', [
            'layout' => $request->attributes->get('layout'),
            'store' => $store,
            'stores_route' => $routes['stores'],
            'store_route' => $routes['store'],
            'form' => $form->createView(),
        ]);
    }

    protected function renderStoreForm(Store $store, Request $request)
    {
        $form = $this->createForm(StoreType::class, $store, [
            'additional_properties' => $this->getLocalizedLocalBusinessProperties(),
        ]);

        $form->handleRequest($request);
        if ($form->isSubmitted() && $form->isValid()) {

            $store = $form->getData();

            $this->getDoctrine()->getManagerForClass(Store::class)->persist($store);
            $this->getDoctrine()->getManagerForClass(Store::class)->flush();

            $this->addFlash(
                'notice',
                $this->get('translator')->trans('global.changesSaved')
            );

            return $this->redirectToRoute('admin_store', [ 'id' => $store->getId() ]);
        }

        $routes = $request->attributes->get('routes');

        return $this->render('@App/store/form.html.twig', [
            'layout' => $request->attributes->get('layout'),
            'store' => $store,
            'form' => $form->createView(),
            'stores_route' => $routes['stores'],
            'store_delivery_new_route' => $routes['store_delivery_new'],
            'store_deliveries_route' => $routes['store_deliveries'],
        ]);
    }

    public function newStoreDeliveryAction($id, Request $request, OrderManager $orderManager, DeliveryManager $deliveryManager)
    {
        $routes = $request->attributes->get('routes');

        $store = $this->getDoctrine()
            ->getRepository(Store::class)
            ->find($id);

        $this->accessControl($store);

        $delivery = Delivery::createWithDefaults();
        $delivery->setStore($store);

        if ($store->getPrefillPickupAddress()) {
            $delivery->getPickup()->setAddress($store->getAddress());
        }

        $form = $this->createDeliveryForm($delivery, [
            'with_store' => false,
            'with_tags' => $this->isGranted('ROLE_ADMIN')
        ]);

        $form->handleRequest($request);
        if ($form->isSubmitted() && $form->isValid()) {

            $delivery = $form->getData();

            if ($store->getCreateOrders()) {

                try {

                    $price = $this->getDeliveryPrice($delivery, $store->getPricingRuleSet(), $deliveryManager);
                    $order = $this->createOrderForDelivery($delivery, $price, $this->getUser());

                    $this->get('sylius.repository.order')->add($order);
                    $orderManager->onDemand($order);
                    $this->get('sylius.manager.order')->flush();

                    return $this->redirectToRoute($routes['success'], ['id' => $id]);

                } catch (NoRuleMatchedException $e) {
                    $message = $this->get('translator')->trans('delivery.price.error.priceCalculation', [], 'validators');
                    $form->addError(new FormError($message));
                }

            } else {

                $this->getDoctrine()
                    ->getManagerForClass(Delivery::class)
                    ->persist($delivery);

                $this->getDoctrine()
                    ->getManagerForClass(Delivery::class)
                    ->flush();

                // TODO Add flash message

                return $this->redirectToRoute($routes['success'], ['id' => $id]);
            }
        }

        return $this->render('@App/store/delivery_form.html.twig', [
            'layout' => $request->attributes->get('layout'),
            'store' => $store,
            'form' => $form->createView(),
            'debug_pricing' => $request->query->getBoolean('debug', false),
            'stores_route' => $routes['stores'],
            'store_route' => $routes['store'],
        ]);
    }

    public function storeAction($id, Request $request)
    {
        $store = $this->getDoctrine()->getRepository(Store::class)->find($id);

        $this->accessControl($store);

        return $this->renderStoreForm($store, $request);
    }

    public function storeDeliveriesAction($id, Request $request)
    {
        $store = $this->getDoctrine()
            ->getRepository(Store::class)
            ->find($id);

        $this->accessControl($store);

        $query = $this->getDoctrine()
            ->getRepository(Delivery::class)
            ->createFindByStoreQuery($store);

        $paginator  = $this->get('knp_paginator');
        $deliveries = $paginator->paginate(
            $query,
            $request->query->getInt('page', 1),
            5
        );

        $routes = $request->attributes->get('routes');

        return $this->render('@App/store/deliveries.html.twig', [
            'layout' => $request->attributes->get('layout'),
            'store' => $store,
            'deliveries' => $deliveries,
            'stores_route' => $routes['stores'],
            'store_route' => $routes['store'],
            'store_delivery_new_route' => $routes['store_delivery_new'],
            'delivery_route' => $routes['delivery'],
        ]);
    }
}
