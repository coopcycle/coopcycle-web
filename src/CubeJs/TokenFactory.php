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
        private string $s3Path,
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
            ->withClaim('s3_path', $this->s3Path)
            ->withClaim('instance', $this->appName);

        foreach ($customClaims as $key => $value) {
            $token = $token->withClaim($key, $value);
        }

        return $token->getToken($this->config->signer(), $this->config->signingKey())->toString();
    }
}
