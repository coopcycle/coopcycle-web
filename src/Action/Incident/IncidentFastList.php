<?php

namespace AppBundle\Action\Incident;

use AppBundle\Entity\Incident\Incident;
use AppBundle\Entity\Incident\IncidentRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\HttpFoundation\Request;

class IncidentFastList
{
    public function __construct(private EntityManagerInterface $entityManager)
    {}

    public function __invoke($data, Request $request): array
    {

        /** @var IncidentRepository $repo */
        $repo = $this->entityManager->getRepository(Incident::class);
        return $repo->getAllIncidents();

    }

}
