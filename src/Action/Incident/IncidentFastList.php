<?php

namespace AppBundle\Action\Incident;

use AppBundle\Entity\Incident\Incident;
use AppBundle\Entity\Incident\IncidentRepository;
use Doctrine\Persistence\ManagerRegistry;
use Doctrine\Persistence\ObjectManager;
use Symfony\Component\HttpFoundation\Request;

class IncidentFastList
{

    private ObjectManager $entityManager;

    public function __construct(ManagerRegistry $doctrine)
    {
        $this->entityManager = $doctrine->getManager();
    }

    public function __invoke($data, Request $request): array
    {

        /** @var IncidentRepository $repo */
        $repo = $this->entityManager->getRepository(Incident::class);
        return $repo->getAllIncidents();

    }

}
