<?php

namespace AppBundle\Controller\Utils;

use AppBundle\Entity\Delivery;
use AppBundle\Entity\Store;
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
                $this->get('translator')->trans('Your changes were saved.')
            );

            return $this->redirectToRoute('admin_stores');
        }

        $routes = $request->attributes->get('routes');

        return $this->render('AppBundle:Store:form.html.twig', [
            'layout' => $request->attributes->get('layout'),
            'store' => $store,
            'form' => $form->createView(),
            'stores_route' => $routes['stores'],
            'store_delivery_route' => $routes['store_delivery'],
        ]);
    }

    public function newStoreDeliveryAction($id, Request $request)
    {
        $deliveryManager = $this->get('coopcycle.delivery.manager');
        $routes = $request->attributes->get('routes');

        $store = $this->getDoctrine()
            ->getRepository(Store::class)
            ->find($id);

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
                $this->get('coopcycle.order_manager')->create($order);
                $this->get('sylius.manager.order')->flush();

                return $this->redirectToRoute($routes['success']);
            }
        }

        return $this->render('@App/Store/deliveryForm.html.twig', [
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

        return $this->renderStoreForm($store, $request);
    }
}
