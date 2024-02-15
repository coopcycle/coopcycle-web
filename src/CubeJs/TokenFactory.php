<?php

namespace AppBundle\CubeJs;

use Lcobucci\JWT\Configuration;

class TokenFactory
{
    private $config;
    private $databaseName;

    public function __construct(Configuration $cubeJsJwtConfiguration, string $databaseName, string $baseUrl)
    {
        $this->config = $cubeJsJwtConfiguration;
        $this->databaseName = $databaseName;
        $this->baseUrl = $baseUrl;
    }

    public function createToken(array $customClaims = array()): string
    {
        // https://github.com/lcobucci/jwt/issues/229
        $now = new \DateTimeImmutable('@' . time());

        $token = $this->config->builder()
            ->expiresAt($now->modify('+1 hour'))
            ->withClaim('database', $this->databaseName)
            ->withClaim('base_url', $this->baseUrl);

        foreach ($customClaims as $key => $value) {
            $token = $token->withClaim($key, $value);
        }

        return $token->getToken($this->config->signer(), $this->config->signingKey())->toString();
    }
}
