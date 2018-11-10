<?php

namespace AppBundle\Controller\Utils;

use AppBundle\Entity\ApiUser;
use AppBundle\Entity\Delivery;
use AppBundle\Entity\Store;
use AppBundle\Form\AddUserType;
use AppBundle\Form\StoreTokenType;
use AppBundle\Form\StoreType;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;

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
            'store_delivery_route' => $routes['store_delivery'],
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

            return $this->redirectToRoute('admin_stores');
        }

        $routes = $request->attributes->get('routes');

        return $this->render('@App/store/form.html.twig', [
            'layout' => $request->attributes->get('layout'),
            'store' => $store,
            'form' => $form->createView(),
            'stores_route' => $routes['stores'],
            'store_delivery_route' => $routes['store_delivery'],
            'store_api_keys_route' => $routes['store_api_keys'],
        ]);
    }

    public function newStoreDeliveryAction($id, Request $request)
    {
        $deliveryManager = $this->get('coopcycle.delivery.manager');
        $routes = $request->attributes->get('routes');

        $store = $this->getDoctrine()
            ->getRepository(Store::class)
            ->find($id);

        $this->accessControl($store);

        $delivery = Delivery::createWithDefaults();
        $delivery->getPickup()->setAddress($store->getAddress());

        $form = $this->createDeliveryForm($delivery, [
            'pricing_rule_set' => $store->getPricingRuleSet(),
        ]);

        $form->handleRequest($request);
        if ($form->isSubmitted() && $form->isValid()) {

            $price = $this->handleDeliveryForm($form, $store->getPricingRuleSet());

            if ($form->isValid()) {

                $delivery = $form->getData();

                $order = $this->createOrderForDelivery($delivery, $price, $this->getUser());

                $this->get('sylius.repository.order')->add($order);
                $this->get('coopcycle.order_manager')->onDemand($order);
                $this->get('sylius.manager.order')->flush();

                $store->addDelivery($delivery);

                $this->getDoctrine()
                    ->getManagerForClass(Store::class)
                    ->flush();

                return $this->redirectToRoute($routes['success']);
            }
        }

        return $this->render('@App/store/delivery_form.html.twig', [
            'layout' => $request->attributes->get('layout'),
            'store' => $store,
            'form' => $form->createView(),
            'stores_route' => $routes['stores'],
            'store_route' => $routes['store'],
            'calculate_price_route' => $routes['calculate_price'],
        ]);
    }

    public function storeAction($id, Request $request)
    {
        $store = $this->getDoctrine()->getRepository(Store::class)->find($id);

        $this->accessControl($store);

        return $this->renderStoreForm($store, $request);
    }

    public function apiKeysAction($id, Request $request)
    {
        $store = $this->getDoctrine()
            ->getRepository(Store::class)
            ->find($id);

        $token = $store->getToken();

        $routes = $request->attributes->get('routes');

        $form = $this->createForm(StoreTokenType::class, $store);

        $form->handleRequest($request);
        if ($form->isSubmitted() && $form->isValid()) {

            $store = $form->getData();

            $this->getDoctrine()
                ->getManagerForClass(Store::class)
                ->flush();

            return $this->redirectToRoute($routes['success'], ['id' => $store->getId()]);
        }

        return $this->render('@App/store/api_keys.html.twig', [
            'layout' => $request->attributes->get('layout'),
            'store' => $store,
            'token' => $token,
            'form' => $form->createView(),
            'stores_route' => $routes['stores'],
        ]);
    }
}
