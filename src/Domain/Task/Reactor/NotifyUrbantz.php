<?php

namespace AppBundle\Domain\Task\Reactor;

use AppBundle\Domain\Task\Event;
use AppBundle\Domain\Task\Event\TaskAssigned;
use AppBundle\Domain\Task\Event\TaskDone;
use AppBundle\Entity\Delivery;
use AppBundle\Entity\Urbantz\Delivery as UrbantzDelivery;
use Carbon\Carbon;
use Doctrine\ORM\EntityManagerInterface;
use Hashids\Hashids;
use Psr\Log\LoggerInterface;
use Psr\Log\NullLogger;
use Symfony\Contracts\HttpClient\Exception\HttpExceptionInterface;
use Symfony\Contracts\HttpClient\Exception\TransportExceptionInterface;
use Symfony\Contracts\HttpClient\HttpClientInterface;

class NotifyUrbantz
{
    private $urbantzClient;
    private $logger;
    private $secret;

    public function __construct(
        HttpClientInterface $urbantzClient,
        private EntityManagerInterface $entityManager,
        string $secret,
        LoggerInterface $logger = null)
    {
        $this->urbantzClient = $urbantzClient;
        $this->entityManager = $entityManager;
        $this->logger = $logger ?? new NullLogger();
        $this->secret = $secret;
    }

    public function __invoke(Event $event)
    {
        $task = $event->getTask();

        if ($event instanceof TaskAssigned && !$task->isDropoff()) {
            return;
        }

        $delivery = $task->getDelivery();

        if (null === $delivery) {
            return;
        }

        // FIXME
        // This will be executed on *EVERY* event,
        // it should be moved to an async message
        $urbantzDelivery = $this->entityManager
            ->getRepository(UrbantzDelivery::class)
            ->findOneBy(['delivery' => $delivery]);

        if (null === $urbantzDelivery) {
            return;
        }

        $this->logger->info(
            sprintf('Notifying Urbantz for event "%s"', $event->messageName())
        );

        switch (get_class($event)) {
            // https://docs.urbantz.com/#operation/AssignTask
            // https://api.urbantz.com/v2/carrier/external/task/ext-123456/complete
            case TaskAssigned::class:
                $this->request($delivery, 'assign', [
                    'arrived' => [
                        'total' => true,
                    ]
                ]);
                break;
            // https://docs.urbantz.com/#operation/CompleteTask
            case TaskDone::class:

                $operation = $task->isDropoff() ? 'complete' : 'start';
                $payload   = $task->isDropoff() ?
                    [
                        'delivered' => [ 'total' => true ]
                    ]
                    :
                    [
                        'departed' => [ 'total'=> true ]
                    ];

                $this->request($delivery, $operation, $payload);
                break;
        }
    }

    private function request(Delivery $delivery, string $operation, array $payload = [])
    {
        $hashids = new Hashids($this->secret, 32);

        $hashid = $hashids->encode($delivery->getId());
        $extTrackId = "dlv_{$hashid}";

        try {

            $this->logger->info(
                sprintf('Sending update to Urbantz for delivery with hashid "%s"', $extTrackId)
            );

            $response = $this->urbantzClient->request('POST', "carrier/external/task/{$extTrackId}/{$operation}", [
                'json' => array_merge([
                    'updatedTime' => Carbon::now()->toIso8601ZuluString(),
                    // Specify how to identify the items to update.
                    // If id or barcode is selected then the field items must be used
                    // otherwise the field total will specify the transition for all
                    'updateType' => 'total',
                ], $payload)
            ]);

            // Need to invoke a method on the Response,
            // to actually throw the Exception here
            // https://github.com/symfony/symfony/issues/34281
            $statusCode = $response->getStatusCode();

        } catch (HttpExceptionInterface | TransportExceptionInterface $e) {
            $this->logger->error($e->getMessage());
        }
    }
}
