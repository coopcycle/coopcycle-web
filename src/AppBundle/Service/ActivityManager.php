<?php

namespace AppBundle\Service;

use AppBundle\Entity\TaskEvent;
use AppBundle\Entity\Sylius\OrderEvent;
use Doctrine\Common\Persistence\ManagerRegistry;
use Doctrine\DBAL\Connection;
use Doctrine\DBAL\Types\Type;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use Symfony\Component\Translation\TranslatorInterface;

class ActivityManager
{
    private $doctrine;
    private $translator;
    private $urlGenerator;

    public function __construct(
        ManagerRegistry $doctrine,
        TranslatorInterface $translator,
        UrlGeneratorInterface $urlGenerator)
    {
        $this->doctrine = $doctrine;
        $this->translator = $translator;
        $this->urlGenerator = $urlGenerator;
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

        $events = array_merge($taskEvents, $orderEvents);

        $events = array_map(function ($event) {

            $data = [
                'name' => $event['name'],
                'metadata' => isset($event['metadata']) ? json_decode($event['metadata'], true) : [],
                'createdAt' => new \DateTime($event['created_at']),
                'aggregateId' => $event['aggregate_id'],
            ];

            if (isset($data['metadata']['username'])) {
                $data['owner'] = $data['metadata']['username'];
            }

            if (0 === strpos($event['name'], 'order:')) {
                $data['aggregateUrl'] = $this->urlGenerator->generate('admin_order', [ 'id' => $event['aggregate_id'] ]);
            }

            $icon = '';
            switch ($data['name']) {
                case 'task:created':
                    $icon = 'plus';
                    break;
                case 'task:assigned':
                    $icon = 'calendar-check-o';
                    break;
                case 'task:unassigned':
                    $icon = 'calendar-times-o';
                    break;
                case 'task:done':
                    $icon = 'check';
                    break;
                case 'task:failed':
                    $icon = 'warning';
                    break;
                case 'order:created':
                    $icon = 'cube';
                    break;
                case 'order:accepted':
                    $icon = 'thumbs-o-up';
                    break;
                case 'order:picked':
                    $icon = 'bicycle';
                    break;
                case 'order:dropped':
                    $icon = 'flag-checkered';
                    break;
                case 'order:fulfilled':
                    $icon = 'check';
                    break;
            }

            $transParams = [
                '%owner%' => $data['owner'],
                '%aggregate_id%' => $data['aggregateId'],
                '%icon%' => $icon
            ];

            if (isset($data['aggregateUrl'])) {
                $transParams['%aggregate_url%'] = $data['aggregateUrl'];
            }

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
