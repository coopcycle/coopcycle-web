<?php

namespace AppBundle\Controller\Utils;

use AppBundle\Entity\Address;
use AppBundle\Entity\Delivery;
use AppBundle\Entity\Store;
use AppBundle\Exception\Pricing\NoRuleMatchedException;
use AppBundle\Form\AddUserType;
use AppBundle\Form\StoreType;
use AppBundle\Form\AddressType;
use AppBundle\Form\DeliveryImportType;
use AppBundle\Service\DeliveryManager;
use AppBundle\Service\OrderManager;
use FOS\UserBundle\Model\UserManagerInterface;
use Knp\Component\Pager\PaginatorInterface;
use Sylius\Component\Taxation\Resolver\TaxRateResolverInterface;
use Symfony\Component\Form\FormError;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;
use Symfony\Contracts\Translation\TranslatorInterface;

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

    public function storeUsersAction($id, Request $request, UserManagerInterface $userManager)
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

            $userManager->updateUser($user);

            return $this->redirectToRoute('admin_store_users', ['id' => $id]);
        }

        return $this->render('store/users.html.twig', [
            'layout' => $request->attributes->get('layout'),
            'store' => $store,
            'users' => $store->getOwners(),
            'stores_route' => $routes['stores'],
            'store_route' => $routes['store'],
            'add_user_form' => $addUserForm->createView(),
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

        return $this->renderStoreAddressForm($store, $address, $request);
    }

    public function newStoreAddressAction($id, Request $request)
    {
        $store = $this->getDoctrine()->getRepository(Store::class)->find($id);

        $this->accessControl($store);

        $address = new Address();

        return $this->renderStoreAddressForm($store, $address, $request);
    }

    protected function renderStoreForm(Store $store, Request $request)
    {
        $form = $this->createForm(StoreType::class, $store);

        $routes = $request->attributes->get('routes');

        $form->handleRequest($request);
        if ($form->isSubmitted() && $form->isValid()) {

            $store = $form->getData();

            $this->getDoctrine()->getManagerForClass(Store::class)->persist($store);
            $this->getDoctrine()->getManagerForClass(Store::class)->flush();

            $this->addFlash(
                'notice',
                $this->get('translator')->trans('global.changesSaved')
            );

            return $this->redirectToRoute($routes['store'], [ 'id' => $store->getId() ]);
        }

        return $this->render('store/form.html.twig', [
            'layout' => $request->attributes->get('layout'),
            'store' => $store,
            'form' => $form->createView(),
            'stores_route' => $routes['stores'],
            'store_delivery_new_route' => $routes['store_delivery_new'],
            'store_deliveries_route' => $routes['store_deliveries'],
            'store_address_new_route' => $routes['store_address_new'],
            'store_address_route' => $routes['store_address'],
        ]);
    }

    protected function renderStoreAddressForm(Store $store, Address $address, Request $request)
    {
        $routes = $request->attributes->get('routes');

        $form = $this->createForm(AddressType::class, $address, [
            'with_name' => true,
            'with_widget' => true,
        ]);

        $form->handleRequest($request);
        if ($form->isSubmitted() && $form->isValid()) {

            $address = $form->getData();

            if (!$store->getAddresses()->contains($address)) {
                $store->addAddress($address);
            }

            // Set as default if no default address is defined yet
            if (null === $store->getAddress()) {
                $store->setAddress($address);
            }

            $this->getDoctrine()->getManagerForClass(Store::class)->flush();

            $this->addFlash(
                'notice',
                $this->get('translator')->trans('global.changesSaved')
            );

            return $this->redirectToRoute($routes['store'], ['id' => $store->getId()]);
        }

        return $this->render('store/address_form.html.twig', [
            'layout' => $request->attributes->get('layout'),
            'store' => $store,
            'stores_route' => $routes['stores'],
            'store_route' => $routes['store'],
            'form' => $form->createView(),
        ]);
    }

    public function newStoreDeliveryAction($id, Request $request,
        OrderManager $orderManager,
        DeliveryManager $deliveryManager,
        TaxRateResolverInterface $taxRateResolver)
    {
        $routes = $request->attributes->get('routes');

        $store = $this->getDoctrine()
            ->getRepository(Store::class)
            ->find($id);

        $this->accessControl($store);

        $delivery = $store->createDelivery();

        $form = $this->createDeliveryForm($delivery, [
            'with_dropoff_recipient_details' => true,
            'with_dropoff_doorstep' => true,
        ]);

        $form->handleRequest($request);
        if ($form->isSubmitted() && $form->isValid()) {

            $delivery = $form->getData();

            if ($store->getCreateOrders()) {

                try {

                    $price = $this->getDeliveryPrice($delivery, $store->getPricingRuleSet(), $deliveryManager);
                    $order = $this->createOrderForDelivery($delivery, $price, $this->getUser()->getCustomer());

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

        $variant = $this->get('sylius.factory.product_variant')
            ->createForDelivery($delivery, 0);

        $rate = $taxRateResolver->resolve($variant, [
            'country' => strtolower($this->getParameter('region_iso')),
        ]);

        return $this->render('store/delivery_form.html.twig', [
            'layout' => $request->attributes->get('layout'),
            'store' => $store,
            'form' => $form->createView(),
            'debug_pricing' => $request->query->getBoolean('debug', false),
            'stores_route' => $routes['stores'],
            'store_route' => $routes['store'],
            'tax_rate' => $rate,
        ]);
    }

    public function storeAction($id, Request $request)
    {
        $store = $this->getDoctrine()->getRepository(Store::class)->find($id);

        $this->accessControl($store);

        return $this->renderStoreForm($store, $request);
    }

    public function storeDeliveriesAction($id, Request $request, TranslatorInterface $translator)
    {
        $store = $this->getDoctrine()
            ->getRepository(Store::class)
            ->find($id);

        $this->accessControl($store);

        $routes = $request->attributes->get('routes');

        $deliveryImportForm = $this->createForm(DeliveryImportType::class);

        $deliveryImportForm->handleRequest($request);
        if ($deliveryImportForm->isSubmitted() && $deliveryImportForm->isValid()) {

            $deliveries = $deliveryImportForm->getData();
            foreach ($deliveries as $delivery) {
                $store->addDelivery($delivery);
                $this->getDoctrine()->getManagerForClass(Delivery::class)->persist($delivery);
            }
            $this->getDoctrine()->getManagerForClass(Delivery::class)->flush();

            $this->addFlash(
                'notice',
                $translator->trans('delivery.import.success_message', ['%count%' => count($deliveries)])
            );

            return $this->redirectToRoute($routes['import_success']);
        }

        $qb = $this->getDoctrine()
            ->getRepository(Delivery::class)
            ->createQueryBuilder('d')
            ->andWhere('d.store = :store')
            ->setParameter('store', $store);

        $deliveries = $this->get('knp_paginator')->paginate(
            $qb,
            $request->query->getInt('page', 1),
            6,
            [
                PaginatorInterface::DEFAULT_SORT_FIELD_NAME => 'd.createdAt',
                PaginatorInterface::DEFAULT_SORT_DIRECTION => 'desc',
                PaginatorInterface::SORT_FIELD_WHITELIST => ['d.createdAt'],
                PaginatorInterface::FILTER_FIELD_WHITELIST => []
            ]
        );

        return $this->render('store/deliveries.html.twig', [
            'layout' => $request->attributes->get('layout'),
            'store' => $store,
            'deliveries' => $deliveries,
            'routes' => $this->getDeliveryRoutes(),
            'stores_route' => $routes['stores'],
            'store_route' => $routes['store'],
            'delivery_import_form' => $deliveryImportForm->createView(),
        ]);
    }
}
