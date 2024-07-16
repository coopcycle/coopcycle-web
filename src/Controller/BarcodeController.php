<?php

namespace AppBundle\Controller;

use ApiPlatform\Core\Api\IriConverterInterface;
use AppBundle\Entity\Delivery;
use AppBundle\Entity\Edifact\EDIFACTMessage;
use AppBundle\Entity\Task;
use AppBundle\Entity\Task\Package;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Serializer\Normalizer\NormalizerInterface;

class BarcodeController extends AbstractController
{
    /**
     * @Route("/api/barcode", name="barcode_api")
     */
    public function barcodeAction(
        IriConverterInterface $iriConverter,
        NormalizerInterface $normalizer,
        Request $request
    ): Response
    {

        if (!$request->get('code', null)) {
           return $this->json(['error' => 'No code provided.'], 400);
        }

        $re = '/6767(?<instance>[0-9]{3})(?<entity>[1-2])(?<id>[0-9]+)(P(?<package>[0-9]+))?(U(?<unit>[0-9]+))?8076/';

        $matches = [];
        preg_match($re, $request->get('code'), $matches, PREG_OFFSET_CAPTURE);

        $entity = match((int)$matches['entity'][0]) {
            1 => Task::class,
            2 => Delivery::class,
            default => null
        };

        if (is_null($entity)) {
            return $this->json(['error' => 'Malformed barcode.'], 400);
        }

        $id = (int)$matches['id'][0];
        $ressource = $this->getDoctrine()->getRepository($entity)->find($id);

        if (is_null($ressource)) {
            return $this->json(['error' => 'No data found.'], 404);
        }


        return $this->json([
            "ressource" => $iriConverter->getIriFromItem($ressource),
            "entity" => $normalizer->normalize($ressource, null, [
                'groups' => ['task', 'package', 'delivery', 'address', 'barcode']
            ])
        ]);
    }

    private function getNonInternalCode(string $code)
    {
        $this->getDoctrine()->getRepository(EDIFACTMessage::class)->findOneBy([

        ]);
    }
}
