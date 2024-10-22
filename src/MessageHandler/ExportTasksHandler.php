<?php

namespace AppBundle\MessageHandler;

use AppBundle\Message\ExportTasks;
use AppBundle\Sylius\Order\OrderInterface;
use AppBundle\Utils\PriceFormatter;
use League\Csv\Writer;
use Symfony\Component\Messenger\Handler\MessageHandlerInterface;
use Doctrine\ORM\EntityManagerInterface;

class ExportTasksHandler implements MessageHandlerInterface
{

    private static $columns = [
        '#',
        '# order',
        'orderCode',
        'orderTotal',
        'orderRevenue',
        'type',
        'address.name',
        'address.streetAddress',
        'address.latlng',
        'address.description',
        'afterDay',
        'afterTime',
        'beforeDay',
        'beforeTime',
        'status',
        'comments',
        'event.DONE.notes',
        'event.FAILED.notes',
        'finishedAtDay',
        'finishedAtTime',
        'courier',
        'tags',
        'address.contactName',
        'organization'
    ];

    /**
    * Replace with ST_AsLatLongText when it will be available
    * @see https://postgis.net/docs/manual-2.0/ST_AsLatLonText.html
    */
    const POSITION_REGEX = "/POINT\((?<lat>[+-]?([0-9]*[.])?[0-9]+)\s(?<long>[+-]?([0-9]*[.])?[0-9]+)\)/";

    public function __construct(
        private EntityManagerInterface $en,
        private PriceFormatter $priceFormatter
    )
    { }

    public function __invoke(ExportTasks $message): ?string
    {

        $afterDate = $message->getFrom()->setTime(0, 0, 0)->format('Y-m-d H:i:s');
        $beforeDate = $message->getTo()->setTime(23, 59, 59)->format('Y-m-d H:i:s');

        $statement = $this->en->getConnection()->prepare(<<<SQL
            WITH task_events AS (
                SELECT
                    task_id,
                    MAX(CASE WHEN name = 'task:done' THEN data->>'notes' END) as done_notes,
                    MAX(CASE WHEN name = 'task:failed' THEN data->>'notes' END) as failed_notes,
                    MAX(CASE WHEN name IN ('task:done', 'task:failed') THEN created_at END) as finished_at
                FROM task_event
                WHERE name IN ('task:done', 'task:failed')
                GROUP BY task_id
            ),
            order_fees AS (
                SELECT
                    order_id,
                    SUM(CASE WHEN type = 'fee' THEN amount ELSE 0 END) as fee_total,
                    SUM(CASE WHEN type = 'stripe_fee' THEN amount ELSE 0 END) as stripe_fee_total
                FROM sylius_adjustment
                WHERE type IN ('fee', 'stripe_fee')
                GROUP BY order_id
            ),
            task_tags AS (
                SELECT
                    resource_id,
                    string_agg(tag.name, ', ') as tags
                FROM tagging TaskTagging
                JOIN tag ON tag.id = TaskTagging.tag_id
                WHERE resource_class = 'AppBundle\Entity\Task'
                GROUP BY TaskTagging.resource_id
            ),
            task_collection_items AS (
                SELECT DISTINCT ON (task_id)
                    task_id
                FROM task_collection_item
                ORDER BY task_id ASC
            ),
            order_row_numbers AS (
                SELECT
                    t.id as task_id,
                    ROW_NUMBER() OVER (PARTITION BY o.number ORDER BY o.id ASC, t.type DESC, t.id ASC) as rn
                FROM task t
                LEFT JOIN delivery d ON d.id = t.delivery_id
                LEFT JOIN sylius_order o ON o.id = d.order_id
            )
            SELECT
                t.id,
                o.id AS order_id,
                o.number AS order_number,
                CASE
                    WHEN o.id IS NULL THEN 0
                    WHEN orn.rn = 1 THEN
                        CASE
                            WHEN o.state IN (:cancelled, :refused) THEN 0
                            ELSE o.total
                        END
                    ELSE NULL
                END AS order_total,
                o.state AS order_state,
                CASE
                    WHEN o.id IS NULL THEN 0
                    WHEN orn.rn = 1 THEN
                        CASE
                            WHEN o.state IN (:cancelled, :refused) THEN 0
                            WHEN COALESCE(of.fee_total, 0) > 0 THEN of.fee_total
                            ELSE o.total
                        END
                    ELSE NULL
                END AS order_fee_total,
                CASE WHEN orn.rn = 1 THEN of.stripe_fee_total ELSE NULL END AS order_stripe_fee_total,
                t.type AS task_type,
                a.name AS address_name,
                a.street_address AS address_street_address,
                ST_AsText(a.geo) AS address_geo,
                a.description AS address_description,
                t.done_after AS after,
                t.done_before AS before,
                t.status AS status,
                t.comments AS comments,
                te.done_notes AS task_done_notes,
                te.failed_notes AS task_failed_notes,
                te.finished_at AS task_finished_at,
                u.username AS task_courier,
                tt.tags,
                a.contact_name AS address_contact_name,
                org.name AS task_organization_name
            FROM task t
            JOIN address a ON a.id = t.address_id
            LEFT JOIN delivery d ON d.id = t.delivery_id
            LEFT JOIN sylius_order o ON o.id = d.order_id
            LEFT JOIN task_package tp ON tp.task_id = t.id
            LEFT JOIN package p ON p.id = tp.package_id
            LEFT JOIN task_events te ON te.task_id = t.id
            LEFT JOIN order_fees of ON of.order_id = o.id
            LEFT JOIN task_tags tt ON tt.resource_id = t.id
            LEFT JOIN api_user u ON u.id = t.assigned_to
            LEFT JOIN organization org ON org.id = t.organization_id
            LEFT JOIN order_row_numbers orn ON orn.task_id = t.id
            WHERE t.done_after >= :after
                AND t.done_before <= :before
            ORDER BY o.id ASC, t.type DESC, t.id ASC;
            SQL);

        $content = $statement->executeQuery([
            'after' => $afterDate,
            'before' => $beforeDate,
            'cancelled' => OrderInterface::STATE_CANCELLED,
            'refused' => OrderInterface::STATE_REFUSED,
        ])->fetchAllAssociative();

        if (empty($content)) {
            return null;
        }

        $csv = Writer::createFromString('');
        $csv->insertOne(self::$columns);

       $records = array_map(function ($row) {
            $after = \DateTimeImmutable::createFromFormat('Y-m-d H:i:s', $row['after']) ?: null;
            $before = \DateTimeImmutable::createFromFormat('Y-m-d H:i:s', $row['before']) ?: null;
            $finishedAt = \DateTimeImmutable::createFromFormat('Y-m-d H:i:s', $row['task_finished_at']) ?: null;
            return [
                intval($row['id']),
                intval($row['order_id']) ?: null,
                $row['order_number'],
                !is_null($row['order_total']) ? $this->priceFormatter->format(intval($row['order_total'])) : null,
                !is_null($row['order_fee_total']) ? $this->priceFormatter->format(intval($row['order_fee_total'])) : null,
                $row['task_type'],
                $row['address_name'],
                $row['address_street_address'],
                preg_replace(self::POSITION_REGEX, '$3,$1', $row['address_geo']),
                $row['address_description'],
                $after?->format('d/m/Y'),
                $after?->format('H:i:s'),
                $before?->format('d/m/Y'),
                $before?->format('H:i:s'),
                $row['status'],
                $row['comments'],
                $row['task_done_notes'],
                $row['task_failed_notes'],
                $finishedAt?->format('d/m/Y'),
                $finishedAt?->format('H:i:s'),
                $row['task_courier'],
                $row['tags'],
                $row['address_contact_name'],
                $row['task_organization_name']
            ];
       }, $content);

        $csv->insertAll($records);
        return $csv->getContent();
    }


}
