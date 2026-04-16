<?php

declare(strict_types=1);

namespace AppBundle\Integration\Rdc\Webhook;

use Psr\Log\LoggerInterface;

class WebhookPayloadParser
{
    public function __construct(
        private readonly LoggerInterface $logger,
    ) {
    }

    public function parse(array $payload): ?array
    {
        if (!isset($payload[0])) {
            $this->logger->warning('RDC webhook payload is empty or missing first element');
            return null;
        }

        $item = $payload[0];

        if (!isset($item['metadata']['loUri'], $item['metadata']['eventType'])) {
            $this->logger->warning('RDC webhook payload missing required metadata fields', [
                'has_lo_uri' => isset($item['metadata']['loUri']),
                'has_event_type' => isset($item['metadata']['eventType']),
            ]);
            return null;
        }

        if (!isset($item['lo'])) {
            $this->logger->warning('RDC webhook payload missing lo field');
            return null;
        }

        return [
            'loUri' => $item['metadata']['loUri'],
            'eventType' => $item['metadata']['eventType'],
            'lo' => $item['lo'],
        ];
    }
}
