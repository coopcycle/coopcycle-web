<?php

namespace AppBundle\Controller\Utils;

use AppBundle\Entity\Organization;
use AppBundle\Entity\Store;
use AppBundle\Form\StoreTokenType;
use AppBundle\Form\StoreType;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;

trait OrganizationTrait
{
    public function organizationListAction(Request $request)
    {
        $organizations = $this->getDoctrine()
            ->getRepository(Organization::class)
            ->findAll();

        $routes = $request->attributes->get('routes');

        return $this->render($request->attributes->get('template'), [
            'layout' => $request->attributes->get('layout'),
            'organizations' => $organizations,
            // 'pages' => $pages,
            // 'page' => $page,
            // 'store_route' => $routes['store'],
            // 'store_delivery_route' => $routes['store_delivery'],
        ]);
    }

    public function organizationAction($id, Request $request)
    {
        $organization = $this->getDoctrine()
            ->getRepository(Organization::class)
            ->find($id);

        $routes = $request->attributes->get('routes');

        return $this->render($request->attributes->get('template'), [
            'layout' => $request->attributes->get('layout'),
            'organization' => $organization,
            // 'pages' => $pages,
            // 'page' => $page,
            // 'store_route' => $routes['store'],
            // 'store_delivery_route' => $routes['store_delivery'],
        ]);
    }
}
