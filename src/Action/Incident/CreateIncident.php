<?php

namespace AppBundle\Action\Incident;

use AppBundle\Entity\Delivery\FailureReason;
use AppBundle\Entity\Delivery\FailureReasonRegistry;
use AppBundle\Entity\Incident\Incident;
use AppBundle\Service\TaskManager;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Security\Core\User\UserInterface;

class CreateIncident
{
    private const DEFAULT_TITLE = 'N/A';

    public function __construct(
        private EntityManagerInterface $em,
        private TaskManager $taskManager,
        private FailureReasonRegistry $failureReasonRegistry
    )
    { }

    public function findDescriptionByCode(?string $code = null): ?string
    {
        if (null === $code) {
            return self::DEFAULT_TITLE;
        }

        $defaults = $this->failureReasonRegistry->getFailureReasons();
        $defaults = array_reduce($defaults, function($carry, $failure_reason) {
            $carry[$failure_reason['code']] = $failure_reason;
            return $carry;
        }, []);

        if (array_key_exists($code, $defaults)) {
            return $defaults[$code]['description'];
        }

        $failure_reason = $this->em->getRepository(FailureReason::class)->findOneBy(['code' => $code]);
        if (!is_null($failure_reason)) {
            return $failure_reason->getDescription();
        }

        // FIXME The title field is actually NOT NULL in database
        return self::DEFAULT_TITLE;
    }

    public function __invoke(Incident $data, ?UserInterface $user, Request $request): Incident
    {
        $title = trim($data->getTitle() ?? '');

        if (empty($title)) {
            $data->setTitle($this->findDescriptionByCode($data->getFailureReasonCode()));
        }

        if (null !== $user) {
            $data->setCreatedBy($user);
        }
        $this->em->persist($data);
        $this->em->flush();

        $this->taskManager->incident(
            $data->getTask(),
            $data->getFailureReasonCode() ?? '',
            $data->getTitle(),
            [
                'incident_id' => $data->getId()
            ],
            $data
        );

        return $data;
    }

}
