<?php

namespace AppBundle\Integration\Rdc\Api;

class RdcClientConfig
{
    public function __construct(
        public readonly string $keycloakBaseUrl,
        public readonly string $keycloakRealm,
        public readonly string $keycloakClientId,
        public readonly string $keycloakClientSecret,
        public readonly string $keycloakUsername,
        public readonly string $keycloakPassword,
        public readonly string $rdcInstanceBaseUrl,
        public readonly string $memberProvider,
        public readonly array $aclAuthorizations = [],
        public readonly ?string $remoteKeycloakRealm = null,
        public readonly ?string $remoteKeycloakUsername = null,
        public readonly ?string $remoteKeycloakPassword = null,
        public readonly ?string $remoteKeycloakClientId = null,
        public readonly ?string $remoteKeycloakClientSecret = null,
        public readonly ?string $remoteMemberProvider = null,
    ) {
        if ($keycloakBaseUrl === '') {
            throw new \InvalidArgumentException('keycloakBaseUrl cannot be empty');
        }
        if ($keycloakRealm === '') {
            throw new \InvalidArgumentException('keycloakRealm cannot be empty');
        }
        if ($rdcInstanceBaseUrl === '') {
            throw new \InvalidArgumentException('rdcInstanceBaseUrl cannot be empty');
        }
    }

    public function getKeycloakTokenUrl(): string
    {
        return sprintf(
            '%s/realms/%s/protocol/openid-connect/token',
            rtrim($this->keycloakBaseUrl, '/'),
            $this->keycloakRealm
        );
    }

    public function getApiBaseUrl(): string
    {
        return rtrim($this->rdcInstanceBaseUrl, '/');
    }
}
