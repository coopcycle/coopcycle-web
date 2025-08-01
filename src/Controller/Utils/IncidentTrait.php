<?php

namespace AppBundle\Controller\Utils;

use AppBundle\Entity\Delivery;
use AppBundle\Entity\Incident\Incident;
use AppBundle\Entity\Incident\IncidentImage;
use AppBundle\Entity\Incident\IncidentRepository;
use Doctrine\ORM\EntityManagerInterface;
use Knp\Component\Pager\PaginatorInterface;
use Liip\ImagineBundle\Service\FilterService;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Vich\UploaderBundle\Templating\Helper\UploaderHelper;
use Symfony\Component\Routing\Attribute\Route;

trait IncidentTrait {

    public function incidentListAction(Request $request, EntityManagerInterface $entityManager, PaginatorInterface $paginator)
    {

        /** @var IncidentRepository $repo */
        $repo =  $entityManager
            ->getRepository(Incident::class);

        $incidents = $repo->getAllIncidents();


        return $this->render($request->attributes->get('template'), $this->auth([
            'layout' => $request->attributes->get('layout'),
            'incidents' => $incidents,
        ]));
    }

    public function incidentAction($id, Request $request, EntityManagerInterface $entityManager) {
        /** @var ?Incident $incident */
        $incident = $entityManager->getRepository(Incident::class)->find($id);

        if (!$incident) {
            throw $this->createNotFoundException();
        }

        /** @var ?Delivery $delivery */
        $delivery = $incident->getTask()->getDelivery();

        $transporterEnabled = $delivery?->getStore()?->isTransporterEnabled() ?? false;

        $isLastmile = !is_null($delivery?->getStore());

        $order = $delivery?->getOrder();

        return $this->render($request->attributes->get('template'), $this->auth([
            'incident' => $incident,
            'delivery' => $delivery,
            'order' => $order,
            'transporterEnabled' => $transporterEnabled,
            'isLastmile' => $isLastmile,
        ]));
    }
}
