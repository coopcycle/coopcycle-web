<?php

declare(strict_types=1);

namespace AppBundle\Integration\Rdc;

use AppBundle\Integration\Rdc\Api\RdcClientInterface;
use AppBundle\Integration\Rdc\Api\RdcException;
use Psr\Log\LoggerInterface;

final class RdcEventDispatcher
{
    public function __construct(private readonly LoggerInterface $logger)
    {
    }

    public function dispatch(
        RdcClientInterface $client,
        string $resourceType,
        string $resourceId,
        array $payload,
        array $documents = []
    ): void {
        if (!empty($documents)) {
            $payload['documents'] = $documents;
        }

        $url = sprintf('/%s/%s/events', $resourceType, $resourceId);

        try {
            $response = $client->post($url, $payload);
            if ($response->getStatusCode() >= 400) {
                throw RdcException::refused(sprintf('POST %s', $url), $response);
            }
        } catch (\Throwable $e) {
            $this->logger->error('Failed to post RDC event', [
                'resource_type' => $resourceType,
                'resource_id' => $resourceId,
                'error' => $e->getMessage(),
            ]);
            throw $e;
        }
    }
}
