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
            'store_deliveries_route' => $routes['store_deliveries'],
        ]);
    }

    public function storeDeliveriesAction($id, Request $request) {

        $routes = $request->attributes->get('routes');

        $store = $this->getDoctrine()
            ->getRepository(Store::class)->find($id);

        $deliveries = $store->getDeliveries();

        $deliveries = $this->get('knp_paginator')->paginate(
            $deliveries,
            $request->query->getInt('page', 1),
            self::ITEMS_PER_PAGE
        );

        return $this->render('AppBundle:Store:storeDeliveries.html.twig', [
            'layout' => $request->attributes->get('layout'),
            'deliveries' => $deliveries,
            'store' => $store,
            'stores_route' => $routes['stores'],
            'store_route' => $routes['store'],
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
            'store_deliveries_route' => $routes['store_deliveries'],
        ]);
    }

    public function newStoreDeliveryAction($id, Request $request)
    {
        $store = $this->getDoctrine()->getRepository(Store::class)->find($id);

        $delivery = new Delivery();
        $delivery->setOriginAddress($store->getAddress());

        return $this->renderDeliveryForm($delivery, $request, $store);
    }

    public function storeAction($id, Request $request)
    {
        $store = $this->getDoctrine()->getRepository(Store::class)->find($id);

        return $this->renderStoreForm($store, $request);
    }
}
