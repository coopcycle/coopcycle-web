<?php

namespace AppBundle\Action\Incident;

use AppBundle\Entity\Incident\Incident;
use AppBundle\Entity\Incident\IncidentEvent;
use AppBundle\Service\TaskManager;
use Carbon\Carbon;
use DateTime;
use Doctrine\Persistence\ManagerRegistry;
use Doctrine\Persistence\ObjectManager;
use IncidentEvent as IncidentEventIncidentEvent;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Security\Core\User\UserInterface;

class IncidentAction
{

    private ObjectManager $entityManager;

    public function __construct(
        ManagerRegistry $doctrine,
        private TaskManager $taskManager,
    )
    {
        $this->entityManager = $doctrine->getManager();
    }

    public function __invoke(Incident $data, UserInterface $user, Request $request): Incident
    {

        $action = $request->request->get("action");

        $allowedActions = [
            IncidentEvent::TYPE_RESCHEDULE,
            IncidentEvent::TYPE_APPLY_PRICE_DIFF,
            IncidentEvent::TYPE_CANCEL_TASK
        ];

        if (empty($action)) {
            throw new \InvalidArgumentException("Action cannot be empty");
        }

        if (!in_array($action, $allowedActions)) {
            throw new \InvalidArgumentException(sprintf("Action %s is not supported", $action));
        }

        $event = new IncidentEvent();
        $event->setIncident($data);
        $event->setCreatedBy($user);

        switch ($action) {
            case IncidentEvent::TYPE_RESCHEDULE:
                $this->reschedule($data, $event, $request);
                break;
            case IncidentEvent::TYPE_APPLY_PRICE_DIFF:
                $this->applyPriceDiff($data, $event, $request);
                break;
            case IncidentEvent::TYPE_CANCEL_TASK:
                $this->cancelTask($data, $event, $request);
                break;
        }

        $data->addEvent($event);

        $this->entityManager->persist($data);
        $this->entityManager->flush();

        return $data;
    }

    private function reschedule(Incident &$data, IncidentEvent &$event, Request $request): void
    {
        $after = $request->request->get("after", null);
        $before = $request->request->get("before", null);

        if (is_null($after) || is_null($before)) {
            throw new \InvalidArgumentException("After and before dates are required");
        }

        $task = $data->getTask();
        $rescheduledAfter = new \DateTime($after);
        $rescheduledBefore = new \DateTime($before);

        $this->taskManager->reschedule($task, $rescheduledAfter, $rescheduledBefore);

        $event->setType(IncidentEvent::TYPE_RESCHEDULE);
        $event->setMetadata([
            'from' => [
                'after' => $task->getAfter()->format(DateTime::ISO8601),
                'before' => $task->getBefore()->format(DateTime::ISO8601)
            ],
            'to' => [
                'after' => $rescheduledAfter->format(DateTime::ISO8601),
                'before' => $rescheduledBefore->format(DateTime::ISO8601)
            ]
        ]);
    }

    private function cancelTask(Incident &$data, IncidentEvent &$event, Request $request): void
    {
        $task = $data->getTask();
        $this->taskManager->cancel($task);
        $event->setType(IncidentEvent::TYPE_CANCEL_TASK);
    }

    private function applyPriceDiff(Incident &$data, IncidentEvent &$event, Request $request): void
    {
        $priceDiff = $request->request->get("diff", null);

        if (is_null($priceDiff)) {
            throw new \InvalidArgumentException("diff is required");
        }

        $event->setType(IncidentEvent::TYPE_APPLY_PRICE_DIFF);

        //TODO:: Merge https://github.com/coopcycle/coopcycle-web/pull/3845
        throw new \Exception("Not implemented");
    }
}
