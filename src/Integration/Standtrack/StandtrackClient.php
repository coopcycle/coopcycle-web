<?php

namespace AppBundle\Integration\Standtrack;

use AppBundle\Service\SettingsManager;
use GuzzleHttp\Client;
use GuzzleHttp\ClientInterface;
use GuzzleHttp\Exception\GuzzleException;
use GuzzleHttp\Psr7\Response;

class StandtrackClient {
   private const BASE_URI = 'https://www.standtrack.com/api/';

    private ClientInterface $client;
    private string $companyGLN;

    public function __construct(
        private readonly string $standtrackApiKey,
        SettingsManager $settingsManager,
        ?ClientInterface $client = null,
        ?string $baseUri = null
    ) {
        $this->companyGLN = $settingsManager->get('company_gln') ?? '';
        $this->client = $client ?? new Client([
            'base_uri' => $baseUri ?? self::BASE_URI,
            'query' => [ 'token' => $this->standtrackApiKey ],
            'headers' => [
                'Content-Type' => 'application/json',
                'Accept' => 'application/json',
            ]
        ]);
    }

    /**
     * Send a delivery event to Standtrack
     *
     * @param DeliveryEvent $event The delivery event to send
     * @throws StandtrackException If the API request fails
     */
    public function sendDeliveryEvent(DeliveryEvent $event): array
    {
        try {
            $response = $this->client->request('POST', 'Events/M030', [
                'json' => ["header" => $event->toArray($this->companyGLN)]
            ]);

            return $this->handleResponse($response);
        } catch (GuzzleException $e) {
            throw new StandtrackException(
                'Failed to send delivery event: ' . $e->getMessage(),
                $e->getCode(),
                $e
            );
        }
    }

    /**
     * Mark a delivery as in progress
     */
    public function markInDelivery(string $shipmentNumber, string $iubCode): array
    {
        $event = new DeliveryEvent(
            EventType::IN_DELIVERY,
            $shipmentNumber,
            [$iubCode]
        );

        return $this->sendDeliveryEvent($event);
    }

    /**
     * Mark a delivery as completed
     */
    public function markDelivered(string $shipmentNumber, string $iubCode): array
    {
        $event = new DeliveryEvent(
            EventType::DELIVERED,
            $shipmentNumber,
            [$iubCode]
        );

        return $this->sendDeliveryEvent($event);
    }

    /**
     * Report a delivery incident
     */
    public function reportIncident(
        string $shipmentNumber,
        string $iubCode,
        IncidentType $incidentType,
        ?string $receiverGln = null
    ): array {
        $event = new DeliveryEvent(
            EventType::INCIDENT,
            $shipmentNumber,
            [$iubCode],
            $receiverGln
        );

        return $this->sendDeliveryEvent($event);
    }

    /**
     * Handle the API response
     */
    private function handleResponse(Response $response): array
    {
        $statusCode = $response->getStatusCode();
        $body = json_decode($response->getBody()->getContents(), true);

        if ($statusCode >= 400) {
            throw new StandtrackException(
                sprintf('API error: %s', $body['message'] ?? 'Unknown error'),
                $statusCode
            );
        }

        return $body;
    }
}
