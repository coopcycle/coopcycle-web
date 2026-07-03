<?php

namespace AppBundle\CubeJs;

use Lcobucci\JWT\Configuration;

class TokenFactory
{
    private $config;

    public function __construct(
        Configuration $cubeJsJwtConfiguration,
        private string $databaseName,
        private string $baseUrl,
        private string $appName)
    {
        $this->config = $cubeJsJwtConfiguration;
    }

    public function createToken(array $customClaims = array()): string
    {
        // https://github.com/lcobucci/jwt/issues/229
        $now = new \DateTimeImmutable('@' . time());

        $token = $this->config->builder()
            ->expiresAt($now->modify('+1 hour'))
            ->withClaim('database', $this->databaseName)
            ->withClaim('base_url', $this->baseUrl)
            ->withClaim('instance', $this->appName);

        foreach ($customClaims as $key => $value) {
            $token = $token->withClaim($key, $value);
        }

        return $token->getToken($this->config->signer(), $this->config->signingKey())->toString();
    }
}
