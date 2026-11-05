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
    private const CHANGE_REQUEST_URL = 'https://bol-api.requestor.example.com/service-requests/sr-1/change-requests?source=true';

    /** @var array<int, array{method: string, url: string, headers: array}> */
    private array $requests = [];

    public function testRemoteRequestsUseTheRemoteLogin(): void
    {
        // Our own token is for our node only
        $tokenManager = $this->createMock(TokenManagerInterface::class);
        $tokenManager->expects($this->never())->method('getValidToken');

        $client = new RdcClient($this->httpClient(), new NullLogger(), $this->config('COOPCYCLE'), $tokenManager);

        $response = $client->postRemote(self::CHANGE_REQUEST_URL, ['operations' => []]);

        $this->assertSame(200, $response->getStatusCode());

        [$token, $changeRequest] = $this->requests;

        // A token from the requestor's realm...
        $this->assertSame('https://keycloak.example.com/realms/REQUESTOR/protocol/openid-connect/token', $token['url']);

        // ...for the change-request, as our member
        $this->assertSame(self::CHANGE_REQUEST_URL, $changeRequest['url']);
        $this->assertSame(['Authorization: Bearer remote-token'], $changeRequest['headers']['authorization']);
        $this->assertSame(['X-BOL-Member-Identifier: BOL.MEMBER.COOPCYCLE'], $changeRequest['headers']['x-bol-member-identifier']);
    }

    public function testTheRemoteMemberMayBeConfiguredWithItsPrefix(): void
    {
        $client = new RdcClient(
            $this->httpClient(),
            new NullLogger(),
            $this->config('BOL.MEMBER.COOPCYCLE'),
            $this->createMock(TokenManagerInterface::class)
        );

        $client->postRemote(self::CHANGE_REQUEST_URL, ['operations' => []]);

        $this->assertSame(['X-BOL-Member-Identifier: BOL.MEMBER.COOPCYCLE'], $this->requests[1]['headers']['x-bol-member-identifier']);
    }

    public function testRemoteRequestsNeedTheRemoteLogin(): void
    {
        $client = new RdcClient(
            $this->httpClient(),
            new NullLogger(),
            $this->config(null),
            $this->createMock(TokenManagerInterface::class)
        );

        $this->expectException(\RuntimeException::class);

        $client->postRemote(self::CHANGE_REQUEST_URL, ['operations' => []]);
    }

    private function httpClient(): MockHttpClient
    {
        return new MockHttpClient(function (string $method, string $url, array $options): MockResponse {
            $this->requests[] = ['method' => $method, 'url' => $url, 'headers' => $options['normalized_headers'] ?? []];

            if (str_ends_with($url, '/protocol/openid-connect/token')) {
                return new MockResponse((string) json_encode(['access_token' => 'remote-token', 'expires_in' => 300]), ['http_code' => 200]);
            }

            return new MockResponse('{}', ['http_code' => 200]);
        });
    }

    private function config(?string $remoteMemberProvider): RdcClientConfig
    {
        return new RdcClientConfig(
            keycloakBaseUrl: 'https://keycloak.example.com',
            keycloakRealm: 'COOPCYCLE',
            keycloakClientId: 'COOPCYCLE',
            keycloakClientSecret: 'secret',
            keycloakUsername: 'coopcycle',
            keycloakPassword: 'password',
            rdcInstanceBaseUrl: 'https://bol-api.coopcycle.example.com',
            memberProvider: 'COOPCYCLE',
            remoteKeycloakRealm: is_null($remoteMemberProvider) ? null : 'REQUESTOR',
            remoteKeycloakUsername: is_null($remoteMemberProvider) ? null : 'coopcycle',
            remoteKeycloakPassword: is_null($remoteMemberProvider) ? null : 'password',
            remoteKeycloakClientId: is_null($remoteMemberProvider) ? null : 'COOPCYCLE',
            remoteKeycloakClientSecret: is_null($remoteMemberProvider) ? null : 'secret',
            remoteMemberProvider: $remoteMemberProvider,
        );
    }
}
