<?php

namespace AppBundle\Controller\Utils;

use AppBundle\Entity\Incident\Incident;
use Knp\Component\Pager\PaginatorInterface;
use Symfony\Component\HttpFoundation\Request;

trait IncidentTrait {

    public function incidentListAction(Request $request, PaginatorInterface $paginator)
    {
        $qb = $this->getDoctrine()
        ->getRepository(Incident::class)
        ->createQueryBuilder('c');

        $INCIDENTS_PER_PAGE = 20;

        $incidents = $paginator->paginate(
            $qb,
            $request->query->getInt('page', 1),
            $INCIDENTS_PER_PAGE,
            [
                PaginatorInterface::DEFAULT_SORT_FIELD_NAME => 'c.createdAt',
                PaginatorInterface::DEFAULT_SORT_DIRECTION => 'desc',
            ],
        );

        $routes = $request->attributes->get('routes');

        return $this->render($request->attributes->get('template'), [
            'incidents' => $incidents,
            'layout' => $request->attributes->get('layout'),
            'incident_route' => $routes['incident'],
            'incident_new_route' => $routes['incident_new'],
        ]);
    }
}
