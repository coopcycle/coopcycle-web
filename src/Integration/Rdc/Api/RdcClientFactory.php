<?php

namespace AppBundle\Integration\Rdc\Api;

use Psr\Log\LoggerInterface;
use Symfony\Contracts\HttpClient\HttpClientInterface;

class RdcClientFactory
{
    public function __construct(
        private readonly HttpClientInterface $httpClient,
        private readonly LoggerInterface $logger,
        private readonly array $rdcConnections = [],
    ) {
    }

    public function create(string $connectionId): ?RdcClientInterface
    {
        if (!isset($this->rdcConnections[$connectionId])) {
            $this->logger->warning('RDC connection not found', [
                'connection_id' => $connectionId,
                'available_connections' => array_keys($this->rdcConnections),
            ]);
            return null;
        }

        $config = $this->rdcConnections[$connectionId];

        if (!($config['enabled'] ?? true)) {
            $this->logger->info('RDC connection is disabled', [
                'connection_id' => $connectionId,
            ]);
            return null;
        }

        return new RdcClient(
            $this->httpClient,
            $this->logger,
            $config['keycloakBaseUrl'] ?? '',
            $config['keycloakRealm'] ?? '',
            $config['keycloakClientId'] ?? '',
            $config['keycloakClientSecret'] ?? '',
            $config['keycloakUsername'] ?? '',
            $config['keycloakPassword'] ?? '',
            $config['instanceBaseUrl'] ?? '',
            $config['memberProvider'] ?? '',
            $config['aclAuthorizations'] ?? [],
        );
    }

    public function getConnectionIds(): array
    {
        return array_keys($this->rdcConnections);
    }
}