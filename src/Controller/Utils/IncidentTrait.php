<?php

namespace AppBundle\Controller\Utils;

use AppBundle\Entity\Delivery;
use AppBundle\Entity\Incident\Incident;
use AppBundle\Entity\Incident\IncidentImage;
use AppBundle\Entity\Incident\IncidentRepository;
use Knp\Component\Pager\PaginatorInterface;
use Liip\ImagineBundle\Service\FilterService;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Vich\UploaderBundle\Templating\Helper\UploaderHelper;
use Symfony\Component\Routing\Annotation\Route;

trait IncidentTrait {

    public function incidentListAction(Request $request, PaginatorInterface $paginator)
    {

        /** @var IncidentRepository $repo */
        $repo =  $this->getDoctrine()
            ->getRepository(Incident::class);

        $incidents = $repo->getAllIncidents();


        return $this->render($request->attributes->get('template'), $this->auth([
            'layout' => $request->attributes->get('layout'),
            'incidents' => $incidents,
        ]));
    }

    public function incidentAction($id, Request $request) {
        /** @var ?Incident $incident */
        $incident = $this->getDoctrine()->getRepository(Incident::class)->find($id);

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

    /**
    * @Route("/media/incident/image/{path}", name="incident_image_public", methods={"GET"})
    */
    public function incidentImagePublicAction($path, Request $request): Response
    {
        $object = $this->getDoctrine()->getRepository(IncidentImage::class)->findOneBy([
            'imageName' => $path
        ]);
        if (is_null($object)) {
            throw $this->createNotFoundException();
        }
        try {
            $imagePath = $this->uploaderHelper->asset($object, 'file');
            $imageBin = $this->incidentImagesFilesystem->read($imagePath);
            $mimeType = $this->incidentImagesFilesystem->mimeType($imagePath);
        } catch (\Exception $e) {
            throw $this->createNotFoundException();
        }
        return new Response($imageBin, 200, [
            'content-type' => $mimeType,
            'Content-Disposition' => sprintf('inline; filename="%s"', $path)
        ]);
    }
}
