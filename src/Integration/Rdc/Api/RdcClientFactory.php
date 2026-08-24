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
            new RdcClientConfig(
                keycloakBaseUrl: $config['keycloakBaseUrl'] ?? throw new \InvalidArgumentException(
                    sprintf('keycloakBaseUrl is required for RDC connection "%s"', $connectionId)
                ),
                keycloakRealm: $config['keycloakRealm'] ?? throw new \InvalidArgumentException(
                    sprintf('keycloakRealm is required for RDC connection "%s"', $connectionId)
                ),
                keycloakClientId: $config['keycloakClientId'] ?? throw new \InvalidArgumentException(
                    sprintf('keycloakClientId is required for RDC connection "%s"', $connectionId)
                ),
                keycloakClientSecret: $config['keycloakClientSecret'] ?? throw new \InvalidArgumentException(
                    sprintf('keycloakClientSecret is required for RDC connection "%s"', $connectionId)
                ),
                keycloakUsername: $config['keycloakUsername'] ?? throw new \InvalidArgumentException(
                    sprintf('keycloakUsername is required for RDC connection "%s"', $connectionId)
                ),
                keycloakPassword: $config['keycloakPassword'] ?? throw new \InvalidArgumentException(
                    sprintf('keycloakPassword is required for RDC connection "%s"', $connectionId)
                ),
                rdcInstanceBaseUrl: $config['instanceBaseUrl'] ?? throw new \InvalidArgumentException(
                    sprintf('instanceBaseUrl is required for RDC connection "%s"', $connectionId)
                ),
                memberProvider: $config['memberProvider'] ?? throw new \InvalidArgumentException(
                    sprintf('memberProvider is required for RDC connection "%s"', $connectionId)
                ),
                aclAuthorizations: $config['aclAuthorizations'] ?? [],
                remoteKeycloakRealm: $config['remoteKeycloakRealm'] ?? null,
                remoteKeycloakUsername: $config['remoteKeycloakUsername'] ?? null,
                remoteKeycloakPassword: $config['remoteKeycloakPassword'] ?? null,
                remoteKeycloakClientId: $config['remoteKeycloakClientId'] ?? null,
                remoteKeycloakClientSecret: $config['remoteKeycloakClientSecret'] ?? null,
                remoteMemberProvider: $config['remoteMemberProvider'] ?? null,
            ),
            new TokenManager(
                $this->httpClient,
                $this->logger,
                new KeycloakTokenConfig(
                    keycloakBaseUrl: $config['keycloakBaseUrl'] ?? throw new \InvalidArgumentException(
                        sprintf('keycloakBaseUrl is required for RDC connection "%s"', $connectionId)
                    ),
                    keycloakRealm: $config['keycloakRealm'] ?? throw new \InvalidArgumentException(
                        sprintf('keycloakRealm is required for RDC connection "%s"', $connectionId)
                    ),
                    keycloakClientId: $config['keycloakClientId'] ?? throw new \InvalidArgumentException(
                        sprintf('keycloakClientId is required for RDC connection "%s"', $connectionId)
                    ),
                    keycloakClientSecret: $config['keycloakClientSecret'] ?? throw new \InvalidArgumentException(
                        sprintf('keycloakClientSecret is required for RDC connection "%s"', $connectionId)
                    ),
                    keycloakUsername: $config['keycloakUsername'] ?? throw new \InvalidArgumentException(
                        sprintf('keycloakUsername is required for RDC connection "%s"', $connectionId)
                    ),
                    keycloakPassword: $config['keycloakPassword'] ?? throw new \InvalidArgumentException(
                        sprintf('keycloakPassword is required for RDC connection "%s"', $connectionId)
                    ),
                ),
            ),
        );
    }

    public function getDefaultClient(): ?RdcClientInterface
    {
        $connectionIds = $this->getConnectionIds();

        if (empty($connectionIds)) {
            $this->logger->warning('No RDC connections configured');
            return null;
        }

        return $this->create($connectionIds[0]);
    }

    /**
     * @return string[]
     */
    public function getConnectionIds(): array
    {
        return array_keys($this->rdcConnections);
    }
}
