<?php

namespace AppBundle\Action\Incident;

use AppBundle\Action\Base;
use AppBundle\Entity\Edifact\EDIFACTMessage;
use AppBundle\Entity\Incident\Incident;
use AppBundle\Entity\Incident\IncidentEvent;
use AppBundle\Entity\Store;
use AppBundle\Service\TaskManager;
use AppBundle\Sylius\Order\AdjustmentInterface;
use DateTime;
use Doctrine\ORM\EntityManagerInterface;
use Sylius\Component\Order\Factory\AdjustmentFactoryInterface;
use Sylius\Component\Order\Processor\OrderProcessorInterface;
use Symfony\Component\HttpFoundation\InputBag;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Security\Core\User\UserInterface;
use Symfony\Contracts\Translation\TranslatorInterface;

class IncidentAction extends Base
{

    public function __construct(
        private readonly EntityManagerInterface $entityManager,
        private readonly TaskManager $taskManager,
        private readonly AdjustmentFactoryInterface $adjustmentFactory,
        private readonly OrderProcessorInterface $orderProcessor,
        private readonly TranslatorInterface $translator,
    )
    {
    }

    public function __invoke(Incident $data, UserInterface $user, Request $request): Incident
    {

        $params = $this->parseRequest($request);
        $action = $params->get("action", null);

        $allowedActions = [
            IncidentEvent::TYPE_RESCHEDULE,
            IncidentEvent::TYPE_APPLY_PRICE_DIFF,
            IncidentEvent::TYPE_CANCEL_TASK,
            IncidentEvent::TYPE_TRANSPORTER_REPORT
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
                $this->reschedule($data, $event, $params);
                break;
            case IncidentEvent::TYPE_APPLY_PRICE_DIFF:
                $this->applyPriceDiff($data, $event, $params);
                break;
            case IncidentEvent::TYPE_CANCEL_TASK:
                $this->cancelTask($data, $event);
                break;
            case IncidentEvent::TYPE_TRANSPORTER_REPORT:
                $this->createTransporterReport($data, $event, $params);
                break;
        }

        $data->addEvent($event);

        $this->entityManager->persist($data);
        $this->entityManager->flush();

        return $data;

    }

    private function reschedule(Incident &$data, IncidentEvent &$event, InputBag $params): void
    {
        $after = $params->get("after", null);
        $before = $params->get("before", null);

        if (is_null($after) || is_null($before)) {
            throw new \InvalidArgumentException("After and before dates are required");
        }

        $task = $data->getTask();
        $rescheduledAfter = new \DateTime($after);
        $rescheduledBefore = new \DateTime($before);


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

        $this->taskManager->reschedule($task, $rescheduledAfter, $rescheduledBefore);
    }

    private function cancelTask(Incident &$data, IncidentEvent &$event): void
    {
        $task = $data->getTask();
        $this->taskManager->cancel($task);
        $event->setType(IncidentEvent::TYPE_CANCEL_TASK);
    }

    private function applyPriceDiff(Incident &$data, IncidentEvent &$event, InputBag $params): void
    {
        $priceDiff = $params->get("diff", null);

        if (is_null($priceDiff)) {
            throw new \InvalidArgumentException("diff is required");
        }

        $order = $data->getTask()->getDelivery()?->getOrder();
        if (is_null($order)) {
            throw new \InvalidArgumentException("There is no order linked to this task");
        }

        if ($order->getTotal() + $priceDiff < 0) {
            throw new \InvalidArgumentException("Price diff cannot be negative");
        }

        $adjustment = $this->adjustmentFactory->createWithData(
            AdjustmentInterface::INCIDENT_ADJUSTMENT,
            $this->translator->trans('order.adjustment_type.incident'),
            $priceDiff);
        $order->addAdjustment($adjustment);

        $this->orderProcessor->process($order);

        $this->entityManager->persist($order);

        $event->setType(IncidentEvent::TYPE_APPLY_PRICE_DIFF);
        $event->setMetadata(["diff" => $priceDiff]);

        //TODO:: Merge https://github.com/coopcycle/coopcycle-web/pull/3845
    }

    private function createTransporterReport(Incident &$data, IncidentEvent &$event, InputBag $params): void
    {
        /** @var ?Store $store */
        $store = $data->getTask()->getDelivery()?->getStore();

        if (is_null($store)) {
            throw new \InvalidArgumentException("There is no store linked to this task");
        }

        //TODO(multitrans): Add support for multi transporters
        if (!$store->isTransporterEnabled()) {
            throw new \InvalidArgumentException("Transporter report cannot be created for store without DBSchenker");
        }

        $failureReason = $params->get("failure_reason", null);
        if (is_null($failureReason)) {
            throw new \InvalidArgumentException("failure_reason is required");
        }

        $createdAt = $params->get("created_at", null);
        if (is_null($createdAt)) {
            throw new \InvalidArgumentException("created_at is required");
        }
        $createdAt = new \DateTime($createdAt);

        /** @var mixed $pods */
        $pods = $params->get("pods", []);
        if (!is_array($pods)) {
            throw new \InvalidArgumentException("pods is required, and must be an array");
        }

        $appointment = $params->get("appointment", null);

        $task = $data->getTask();
        $importEDI = $task->getImportMessage();
        if (is_null($importEDI)) {
            throw new \InvalidArgumentException("There is no import message linked to this task");
        }

        $ediMessage = new EDIFACTMessage();
        $ediMessage->setMessageType(EDIFACTMessage::MESSAGE_TYPE_REPORT);
        $ediMessage->setTransporter($importEDI->getTransporter());
        $ediMessage->setDirection(EDIFACTMessage::DIRECTION_OUTBOUND);
        $ediMessage->setReference($importEDI->getReference());
        $ediMessage->setSubMessageType($failureReason);
        $ediMessage->setCreatedAt($createdAt);
        $ediMessage->setPods($pods);

        if (!is_null($appointment)) {
            $ediMessage->setAppointment(new \DateTime($appointment));
        }

        $task->addEdifactMessage($ediMessage);

        $this->entityManager->persist($ediMessage);
        $this->entityManager->persist($task);


        $event->setType(IncidentEvent::TYPE_TRANSPORTER_REPORT);

    }
}
