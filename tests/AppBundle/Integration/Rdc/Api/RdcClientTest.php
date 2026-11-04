<?php

declare(strict_types=1);

namespace Tests\AppBundle\Integration\Rdc\Api;

use AppBundle\Integration\Rdc\Api\RdcClient;
use AppBundle\Integration\Rdc\Api\RdcClientConfig;
use AppBundle\Integration\Rdc\Api\TokenManagerInterface;
use PHPUnit\Framework\TestCase;
use Psr\Log\NullLogger;
use Symfony\Component\HttpClient\MockHttpClient;
use Symfony\Component\HttpClient\Response\MockResponse;

class RdcClientTest extends TestCase
{
    public function testRemoteRequestsUseOurOwnCredentials(): void
    {
        $httpClient = new MockHttpClient(function (string $method, string $url, array $options): MockResponse {
            $this->assertSame('POST', $method);
            $this->assertSame('https://bol-api.nwp.example.com/service-requests/C-26-1234-1/change-requests?source=true', $url);
            $this->assertContains('Authorization: Bearer our-token', $options['headers']);
            $this->assertContains('X-BOL-Member-Identifier: BOL.MEMBER.COOPCYCLE', $options['headers']);

            return new MockResponse('{}', ['http_code' => 201]);
        });

        $tokenManager = $this->createMock(TokenManagerInterface::class);
        $tokenManager->method('getValidToken')->willReturn('our-token');

        $client = new RdcClient($httpClient, new NullLogger(), new RdcClientConfig(
            keycloakBaseUrl: 'https://keycloak.example.com',
            keycloakRealm: 'COOPCYCLE',
            keycloakClientId: 'COOPCYCLE',
            keycloakClientSecret: 'secret',
            keycloakUsername: 'coopcycle',
            keycloakPassword: 'password',
            rdcInstanceBaseUrl: 'https://bol-api.coopcycle.example.com',
            memberProvider: 'COOPCYCLE',
        ), $tokenManager);

        $response = $client->postRemote(
            'https://bol-api.nwp.example.com/service-requests/C-26-1234-1/change-requests?source=true',
            ['operations' => []],
        );

        $this->assertSame(201, $response->getStatusCode());
        $this->assertSame(1, $httpClient->getRequestsCount());
    }
}
