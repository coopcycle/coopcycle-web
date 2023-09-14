<?php

namespace AppBundle\Service;

use AppBundle\Domain\HasIconInterface;
use AppBundle\Domain\Order\Event as OrderEvents;
use AppBundle\Domain\Task\Event as TaskEvents;
use AppBundle\Entity\TaskEvent;
use AppBundle\Entity\Sylius\OrderEvent;
use Doctrine\Persistence\ManagerRegistry;
use Doctrine\DBAL\Connection;
use Doctrine\DBAL\Types\Type;
use Symfony\Contracts\Translation\TranslatorInterface;

class ActivityManager
{
    private $doctrine;
    private $translator;

    private $events = [
        OrderEvents\OrderCreated::class,
        OrderEvents\OrderAccepted::class,
        OrderEvents\OrderRefused::class,
        OrderEvents\OrderPicked::class,
        OrderEvents\OrderDropped::class,
        OrderEvents\OrderFulfilled::class,
        TaskEvents\TaskCreated::class,
        TaskEvents\TaskAssigned::class,
        TaskEvents\TaskUnassigned::class,
        TaskEvents\TaskStarted::class,
        TaskEvents\TaskDone::class,
        TaskEvents\TaskFailed::class,
        TaskEvents\TaskCancelled::class,
        TaskEvents\TaskRescheduled::class,
    ];

    public function __construct(
        ManagerRegistry $doctrine,
        TranslatorInterface $translator)
    {
        $this->doctrine = $doctrine;
        $this->translator = $translator;
    }

    private function getTaskEvents(\DateTime $date)
    {
        $connection = $this->doctrine->getManager()->getConnection();

        $dateAsString = $date->format('Y-m-d H:i:s');

        $stmt = $connection->prepare('SELECT e.name, e.created_at, e.data, e.metadata, e.task_id AS aggregate_id FROM task_event e WHERE DATE(e.created_at) = :date');
        $stmt->bindParam('date', $dateAsString);
        $stmt->execute();

        return $stmt->fetchAll();
    }

    private function getOrderEvents(\DateTime $date)
    {
        $connection = $this->doctrine->getManager()->getConnection();

        $excluded = [
            'order:email_sent',
            'order:checkout_succeeded'
        ];

        $stmt = $connection->executeQuery('SELECT e.type AS name, e.created_at, e.data, e.metadata, e.aggregate_id FROM sylius_order_event e WHERE e.type NOT IN (:excluded) and DATE(e.created_at) = :date',
            [
                'excluded' => $excluded,
                'date' => $date
            ],
            [
                'excluded' => Connection::PARAM_STR_ARRAY,
                'date' => Type::DATE
            ]
        );

        return $stmt->fetchAll();
    }

    public function getEventsByDate(\DateTime $date)
    {
        $taskEvents = $this->getTaskEvents($date);
        $orderEvents = $this->getOrderEvents($date);

        $eventsByName = [];
        foreach ($this->events as $event) {
            $eventsByName[$event::messageName()] = $event;
        }

        $events = array_merge($taskEvents, $orderEvents);

        $events = array_map(function ($event) use ($eventsByName) {

            $data = [
                'name' => $event['name'],
                'metadata' => isset($event['metadata']) ? json_decode($event['metadata'], true) : [],
                'createdAt' => new \DateTime($event['created_at']),
                'aggregateId' => $event['aggregate_id'],
            ];

            if (isset($data['metadata']['username'])) {
                $data['owner'] = $data['metadata']['username'];
            }

            if (isset($eventsByName[$event['name']])) {
                $eventClass = $eventsByName[$event['name']];
                if (in_array(HasIconInterface::class, class_implements($eventClass))) {
                    $data['icon'] = $eventClass::iconName();
                }
            }

            $transParams = [
                '%owner%' => $data['owner'],
                '%aggregate_id%' => $data['aggregateId'],
            ];

            $key = 'activity.' . str_replace(':', '.', $data['name']);
            $data['forHumans'] = $this->translator->trans($key, $transParams);

            return $data;

        }, $events);

        usort($events, function ($a, $b) {

            return $a['createdAt'] < $b['createdAt'] ? 1 : -1;
        });

        return $events;
    }
}
