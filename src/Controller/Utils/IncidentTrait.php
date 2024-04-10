<?php

namespace AppBundle\Controller\Utils;

use AppBundle\Entity\Incident\Incident;
use AppBundle\Entity\Incident\IncidentImage;
use Knp\Component\Pager\PaginatorInterface;
use Liip\ImagineBundle\Service\FilterService;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Vich\UploaderBundle\Templating\Helper\UploaderHelper;
use Symfony\Component\Routing\Annotation\Route;

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

    public function incidentAction($id, Request $request) {
        /** @var ?Incident $incident */
        $incident = $this->getDoctrine()->getRepository(Incident::class)->find($id);

        // $images = $incident->getImages()->map(function (IncidentImage $image) use ($uploaderHelper, $filterService) {
        //     $path = $uploaderHelper->asset($image, 'file');
        //     return [
        //         'path' => $path,
        //         'thumbnail' => $filterService->getUrlOfFilteredImage($path, 'incident_image_thumbnail'),
        //     ];
        // });

        if (!$incident) {
            throw $this->createNotFoundException();
        }

        $delivery = $incident->getTask()?->getDelivery();

        $order = $delivery?->getOrder();

        return $this->render($request->attributes->get('template'), $this->auth([
            'incident' => $incident,
            'delivery' => $delivery,
            'order' => $order
        ]));
    }

    /**
    * @Route("/incident/image/{id}.jpg", name="incident_image", methods={"GET"})
    */
    public function incidentImageAction($id, Request $request) {
        $object = $this->getDoctrine()->getRepository(IncidentImage::class)->find($id);
        if (is_null($object)) {
            throw $this->createNotFoundException();
        }
        try {
            $imagePath = $this->uploaderHelper->asset($object, 'file');
            $imageBin = $this->incidentImagesFilesystem->read($imagePath);
        } catch (\Exception $e) {
            throw $this->createNotFoundException();
        }
        return new Response($imageBin, 200, [
            'content-type' => 'image/jpeg',
            'Content-Disposition' => sprintf('inline; filename="%s"', $object->getImageName())
        ]);
    }
}
