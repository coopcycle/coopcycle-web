<?php

namespace AppBundle\Integration\Rdc\Api;

class KeycloakTokenConfig
{
    public function __construct(
        public readonly string $keycloakBaseUrl,
        public readonly string $keycloakRealm,
        public readonly string $keycloakClientId,
        public readonly string $keycloakClientSecret,
        public readonly string $keycloakUsername,
        public readonly string $keycloakPassword,
    ) {
    }

    public function getKeycloakTokenUrl(): string
    {
        return sprintf(
            '%s/realms/%s/protocol/openid-connect/token',
            rtrim($this->keycloakBaseUrl, '/'),
            $this->keycloakRealm
        );
    }
}