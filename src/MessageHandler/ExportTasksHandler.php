<?php

namespace AppBundle\MessageHandler;

use AppBundle\CubeJs\TokenFactory;
use AppBundle\Message\ExportTasks;
use AppBundle\Sylius\Order\OrderInterface;
use AppBundle\Utils\GeoUtils;
use AppBundle\Utils\PriceFormatter;
use League\Csv\Writer;
use Symfony\Component\Messenger\Handler\MessageHandlerInterface;
use Symfony\Contracts\HttpClient\HttpClientInterface;

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

    private static $ignoredStates = [
        OrderInterface::STATE_CANCELLED,
        OrderInterface::STATE_REFUSED,
    ];

    public function __construct(
        private HttpClientInterface $cubejsClient,
        private TokenFactory $tokenFactory,
        private PriceFormatter $priceFormatter
    )
    { }

    public function __invoke(ExportTasks $message): string
    {
        $cubeJsToken = $this->tokenFactory->createToken();

            // we have to add 1 day to range selected because Cube does not include the selected date in the filter
            $afterDate = $message->getFrom()->modify('-1 day')->format('Y-m-d');
            $beforeDate = $message->getTo()->modify('+1 day')->format('Y-m-d');

            $response = $this->cubejsClient->request('POST', 'load', [
                'headers' => [
                    'Authorization' => $cubeJsToken,
                    'Content-Type' => 'application/json',
                ],
                'body' => json_encode([
                  'query' => [
                    'order' => [
                        ["TasksExportUnified.orderId","asc"],
                        ["TasksExportUnified.taskType","desc"],
                    ],
                    'filters' => [
                      [
                          'member' => "TasksExportUnified.taskAfterDayTime",
                          'operator' => "afterDate",
                          'values' => [$afterDate]
                      ],
                      [
                          'member' => "TasksExportUnified.taskBeforeDayTime",
                          'operator' => "beforeDate",
                          'values' => [$beforeDate]
                      ]
                    ],
                    'dimensions' => [
                        "TasksExportUnified.taskId",
                        "TasksExportUnified.orderId",
                        "TasksExportUnified.orderNumber",
                        "TasksExportUnified.orderTotal",
                        "TasksExportUnified.orderFeeTotal",
                        "TasksExportUnified.orderState",
                        "TasksExportUnified.taskType",
                        "TasksExportUnified.addressName",
                        "TasksExportUnified.addressStreetAddress",
                        "TasksExportUnified.addressGeo",
                        "TasksExportUnified.addressDescription",
                        "TasksExportUnified.taskAfterDay",
                        "TasksExportUnified.taskAfterTime",
                        "TasksExportUnified.taskBeforeDay",
                        "TasksExportUnified.taskBeforeTime",
                        "TasksExportUnified.taskStatus",
                        "TasksExportUnified.taskComments",
                        "TasksExportUnified.taskDoneNotes",
                        "TasksExportUnified.taskFailedNotes",
                        "TasksExportUnified.taskFinishedAtDay",
                        "TasksExportUnified.taskFinishedAtTime",
                        "TasksExportUnified.taskCourier",
                        "TasksExportUnified.taskTags",
                        "TasksExportUnified.addressContactName",
                        "TasksExportUnified.taskOrganizationName",
                        "TasksExportUnified.taskPosition"
                    ]
                  ]
                ])
            ]);

            // Need to invoke a method on the Response,
            // to actually throw the Exception here
            // https://github.com/symfony/symfony/issues/34281
            // https://symfony.com/doc/5.4/http_client.html#handling-exceptions
            $content = $response->getContent();

            $resultSet = json_decode($content, true);

            $csv = Writer::createFromString('');
            $csv->insertOne(self::$columns);

            $records = [];
            foreach ($resultSet['data'] as $resultObject) {

                $geo = GeoUtils::asGeoCoordinates($resultObject['TasksExportUnified.addressGeo']);

                $records[] = array_combine(self::$columns, [
                    $resultObject['TasksExportUnified.taskId'],
                    $resultObject['TasksExportUnified.orderId'],
                    $resultObject['TasksExportUnified.orderNumber'],
                    $this->priceFormatter->format($this->getOrderTotal(
                        $resultObject['TasksExportUnified.orderState'],
                        $resultObject['TasksExportUnified.orderTotal']
                    )),
                    $this->priceFormatter->format($this->getOrderRevenue(
                        $resultObject['TasksExportUnified.orderState'],
                        $resultObject['TasksExportUnified.orderTotal'],
                        $resultObject['TasksExportUnified.orderFeeTotal']
                    )),
                    $resultObject['TasksExportUnified.taskType'],
                    $resultObject['TasksExportUnified.addressName'],
                    $resultObject['TasksExportUnified.addressStreetAddress'],
                    implode(',', [$geo->getLatitude(), $geo->getLongitude()]),
                    $resultObject['TasksExportUnified.addressDescription'],
                    $resultObject['TasksExportUnified.taskAfterDay'],
                    $resultObject['TasksExportUnified.taskAfterTime'],
                    $resultObject['TasksExportUnified.taskBeforeDay'],
                    $resultObject['TasksExportUnified.taskBeforeTime'],
                    $resultObject['TasksExportUnified.taskStatus'],
                    $resultObject['TasksExportUnified.taskComments'],
                    $resultObject['TasksExportUnified.taskDoneNotes'],
                    $resultObject['TasksExportUnified.taskFailedNotes'],
                    $resultObject['TasksExportUnified.taskFinishedAtDay'],
                    $resultObject['TasksExportUnified.taskFinishedAtTime'],
                    $resultObject['TasksExportUnified.taskCourier'],
                    $resultObject['TasksExportUnified.taskTags'],
                    $resultObject['TasksExportUnified.addressContactName'],
                    $resultObject['TasksExportUnified.taskOrganizationName'],
                ]);
            }

            // Make sure the order total only appears in one row
            $orderNumbers = [];
            $records = array_map(function ($row) use (&$orderNumbers) {

                if (empty($row['orderCode'])) {

                    return $row;
                }

                if (in_array($row['orderCode'], $orderNumbers)) {

                    $row['orderTotal'] = '';
                    $row['orderRevenue'] = '';

                    return $row;
                }

                $orderNumbers[] = $row['orderCode'];

                return $row;

            }, $records);

            $csv->insertAll($records);
            return $csv->getContent();
    }

    private function getOrderTotal(?string $state, ?int $total): int
    {
        if (in_array($state, self::$ignoredStates)) {

            return 0;
        }

        return $total ?? 0;
    }

    private function getOrderRevenue(?string $state, ?int $total, ?int $feeTotal): int
    {
        if (in_array($state, self::$ignoredStates)) {

            return 0;
        }

        return ($feeTotal > 0 ? $feeTotal : $total) ?? 0;
    }

}
