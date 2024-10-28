<?php

namespace AppBundle\Action\Task;

use AppBundle\Entity\Delivery\FailureReasonRegistry;
use AppBundle\Entity\Incident\Incident;
use AppBundle\Entity\LocalBusiness;
use AppBundle\Entity\Model\CustomFailureReasonInterface;
use AppBundle\Entity\Organization;
use AppBundle\Entity\Store;
use AppBundle\Entity\Task;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\HttpFoundation\Request;

class FailureReasons
{
    public function __construct(
        private EntityManagerInterface $em,
        private FailureReasonRegistry $failureReasonRegistry,
        private array $failureReasonsResolvers
    )
    { }

    private function getDefaultReasons(Task $task): array
    {
        $reasons = $this->failureReasonRegistry->getFailureReasons();

        foreach ($this->failureReasonsResolvers as $failureReasonsResolver) {
            if ($failureReasonsResolver->supports($task)) {
                $reasons = array_merge($reasons, $failureReasonsResolver->getFailureReasons($task));
            }
        }

        return $reasons;
    }

    private function getFailureReasons(
        Task $task,
        CustomFailureReasonInterface $entity,
        bool $transporter
    ): array
    {
        if (
            $transporter &&
            $entity instanceof Store &&
            $entity->isTransporterEnabled()
        ) {
            //TODO: Support multi transporter
            return $this->failureReasonRegistry->getFailureReasons('dbschenker');
        }
        $set = $entity->getFailureReasonSet();
        if (is_null($set)) {
            return $this->getDefaultReasons($task);
        }
        return $set->getReasons()->toArray();
    }

    public function __invoke($data, Request $request)
    {
        $org = $data->getOrganization();

        $transporter = boolval($request->get('transporter', false));

        if (null !== $org) {
            $reverse = $this->em->getRepository(Organization::class)
                ->reverseFindByOrganization($org);
            if (null !== $reverse) {
                return $this->getFailureReasons($data, $reverse, $transporter);
            }
        }

        return $this->getDefaultReasons($data);
    }
}
