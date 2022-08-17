<?php

namespace AppBundle\MessageHandler;

use ApiPlatform\Core\Api\IriConverterInterface;
use AppBundle\Entity\Delivery;
use AppBundle\Message\WoopitWebhook;
use BenjaminFavre\OAuthHttpClient\OAuthHttpClient;
use Carbon\Carbon;
use Doctrine\ORM\EntityManagerInterface;
use Hashids\Hashids;
use Psr\Log\LoggerInterface;
use Psr\Log\NullLogger;
use Symfony\Component\Messenger\Handler\MessageHandlerInterface;
use Symfony\Contracts\HttpClient\Exception\HttpExceptionInterface;
use Symfony\Contracts\HttpClient\Exception\TransportExceptionInterface;

class WoopitWebhookHandler implements MessageHandlerInterface
{
    private $apiVersion = '1.6.0';

    public function __construct(
        OAuthHttpClient $woopitClient,
        IriConverterInterface $iriConverter,
        EntityManagerInterface $entityManager,
        Hashids $hashids12,
        LoggerInterface $logger = null)
    {
        $this->woopitClient = $woopitClient;
        $this->iriConverter = $iriConverter;
        $this->entityManager = $entityManager;
        $this->hashids12 = $hashids12;
        $this->logger = $logger ?? new NullLogger();
    }

    public function __invoke(WoopitWebhook $message)
    {
        $delivery = $this->iriConverter->getItemFromIri($message->getObject());

        if (!$delivery instanceof Delivery) {
            return;
        }

        $status = null;
        switch ($message->getEvent()) {
            case 'delivery.assigned':
                $status = 'DELIVERY_TEAM_ASSIGNED';
                break;
            case 'delivery.started':
                $status = 'DELIVERY_PICK_UP_STARTED';
                break;
            case 'delivery.in_progress':
                $status = 'DELIVERY_IN_PROGRESS';
                break;
            case 'delivery.picked':
                $status = 'DELIVERY_PICK_UP_OK';
                break;
            case 'delivery.completed':
                $status = 'DELIVERY_OK';
                break;
            case 'delivery.pickup_failed':
                $status = 'DELIVERY_PICK_UP_KO';
                break;
            case 'delivery.failed':
                $status = 'DELIVERY_KO';
                break;
        }

        if (null !== $status) {
            $this->logger->info(
                sprintf('Notifying Woopit for new status "%s"', $status)
            );

            $this->request($delivery, [
                'status' => $status
            ]);
        }

    }

    private function request(Delivery $delivery, array $payload = [])
    {
        $deliveryId = $this->hashids12->encode($delivery->getId());

        try {
            $this->logger->info(
                sprintf('Sending status update to Woopit for delivery with id %s', $deliveryId)
            );

            $response = $this->woopitClient->request('PUT', "deliveries/${deliveryId}/status", [
                'headers' => [
                    'x-api-version' => $this->apiVersion
                ],
                'json' => array_merge(
                    [
                        'date' => Carbon::now(),
                        'comment' => 'N/A'
                    ],
                    $payload
                )
            ]);

            $statusCode = $response->getStatusCode();

            switch($statusCode) {
                case 202:
                    $this->logger->info(
                        sprintf('Status update request processed successfully for delivery with id %s', $deliveryId)
                    );
                    break;
                case 400:
                    $responseData = json_decode((string) $response->getContent(false), true);
                    $this->logger->error(
                        sprintf('Missing and/or incorrect items in the body. Reasons: %s', $responseData['message'])
                    );
                    break;
                case 404:
                    $this->logger->error(
                        sprintf('Delivery with id %s was not found', $deliveryId)
                    );
                    break;
                default:
                    $this->logger->warning(
                        sprintf('Status code %d not handled', $statusCode)
                    );
                    break;
            }
        } catch (HttpExceptionInterface | TransportExceptionInterface $e) {
            $this->logger->error($e->getMessage());
        }
    }

}
