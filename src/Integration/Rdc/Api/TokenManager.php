<?php

namespace AppBundle\Integration\Rdc\Api;

use Psr\Log\LoggerInterface;
use Symfony\Contracts\HttpClient\HttpClientInterface;

class TokenManager implements TokenManagerInterface
{
    private const TOKEN_BUFFER_SECONDS = 60;

    private ?string $cachedToken = null;
    private int $tokenExpiresAt = 0;

    public function __construct(
        private readonly HttpClientInterface $httpClient,
        private readonly LoggerInterface $logger,
        private readonly KeycloakTokenConfig $config,
    ) {
    }

    public function getValidToken(): string
    {
        if ($this->cachedToken !== null && $this->tokenExpiresAt > time() + self::TOKEN_BUFFER_SECONDS) {
            return $this->cachedToken;
        }

        return $this->refreshToken();
    }

    public function refreshToken(): string
    {
        $this->logger->info('Refreshing RDC access token');

        $url = $this->config->getKeycloakTokenUrl();

        try {
            $response = $this->httpClient->request('POST', $url, [
                'headers' => ['Content-Type' => 'application/x-www-form-urlencoded'],
                'body' => [
                    'grant_type' => 'password',
                    'client_id' => $this->config->keycloakClientId,
                    'client_secret' => $this->config->keycloakClientSecret,
                    'username' => $this->config->keycloakUsername,
                    'password' => $this->config->keycloakPassword,
                ],
            ]);

            $data = $response->toArray();

            $this->cachedToken = $data['access_token'];
            $this->tokenExpiresAt = time() + (int) $data['expires_in'];

            $this->logger->info('Token refresh successful', [
                'expires_in' => $data['expires_in'],
                'buffer_seconds' => self::TOKEN_BUFFER_SECONDS,
            ]);

            return $this->cachedToken;

        } catch (\Throwable $e) {
            $this->logger->error('Token refresh failed', [
                'error' => $e->getMessage(),
            ]);

            throw RdcException::tokenRefreshFailed($e);
        }
    }

    public function revokeToken(): void
    {
        $this->cachedToken = null;
        $this->tokenExpiresAt = 0;
    }
}