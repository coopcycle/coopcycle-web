<?php

namespace AppBundle\Action\Trailer;

use ApiPlatform\Core\Api\IriConverterInterface;
use AppBundle\Entity\Trailer;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\HttpFoundation\Request;

final class SetVehicles
{
    public function __construct(
        private IriConverterInterface $iriConverterInterface,
        private EntityManagerInterface $entityManagerInterface
    )
    {}

    public function __invoke(Request $request)
    {
        $trailer = $this->entityManagerInterface->getRepository(Trailer::class)->findOneBy(['id' => $request->get('id')]);
        $data = json_decode($request->getContent(), true);
        $vehiclesIri = $data['compatibleVehicles'];
        $vehicles = [];
        foreach ($vehiclesIri as $iri) {
            array_push($vehicles, $this->iriConverterInterface->getItemFromIri($iri));
        }
        $trailer->setCompatibleVehicles($vehicles);

        $this->entityManagerInterface->persist($trailer);
        $this->entityManagerInterface->flush();

        return $trailer;
    }

}
