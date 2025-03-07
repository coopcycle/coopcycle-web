<?php

namespace AppBundle\Dabba;

use ApiPlatform\Core\Api\IriConverterInterface;
use AppBundle\Dabba\OAuthCredentialsInterface;
use AppBundle\Sylius\Order\OrderInterface;
use Doctrine\ORM\EntityManagerInterface;
use Lexik\Bundle\JWTAuthenticationBundle\Encoder\JWTEncoderInterface;
use Symfony\Contracts\HttpClient\HttpClientInterface;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use Symfony\Contracts\HttpClient\ResponseInterface;
use Symfony\Contracts\HttpClient\Exception\ClientExceptionInterface;

class Client
{
    private $client;

    public function __construct(
        HttpClientInterface $dabbaClient,
        private UrlGeneratorInterface $urlGenerator,
        private EntityManagerInterface $entityManager,
        private JWTEncoderInterface $jwtEncoder,
        private IriConverterInterface $iriConverter,
        private string $baseUrl,
        private string $clientId,
        private string $clientSecret)
    {
        $this->client = $dabbaClient;
        $this->urlGenerator = $urlGenerator;
        $this->entityManager = $entityManager;
        $this->jwtEncoder = $jwtEncoder;
        $this->iriConverter = $iriConverter;
        $this->baseUrl = $baseUrl;
        $this->clientId = $clientId;
        $this->clientSecret = $clientSecret;
    }

    protected function request(string $method, string $url, array $options = []): ResponseInterface
    {
        if (isset($options['extra']['oauth_credentials'])) {
            $customer = $options['extra']['oauth_credentials'];

            $headers = $options['headers'] ?? [];
            $headers['Authorization'] = sprintf('Bearer %s', $customer->getDabbaAccessToken());

            $options['headers'] = $headers;
        }

        $response = $this->client->request($method, $url, $options);

        if (401 === $response->getStatusCode()) {

            $customer = $options['extra']['oauth_credentials'];

            $response = $this->client->request('POST', 'api/accessToken', [
                'body' => [
                    'grant_type' => 'refresh_token',
                    'client_id' => $this->clientId,
                    'client_secret' => $this->clientSecret,
                    'refresh_token' => $customer->getDabbaRefreshToken(),
                ]
            ]);

            $data = $response->toArray();

            $customer->setDabbaAccessToken($data['access_token']);
            $customer->setDabbaRefreshToken($data['refresh_token']);

            $options['extra']['oauth_credentials'] = $customer;

            $this->entityManager->flush();

            return $this->request($method, $url, $options);
        }

        return $response;
    }

    public function currentUser(OAuthCredentialsInterface $customer): array
    {
        $response = $this->request(
            'GET',
            'api/users/current',
            [
                'extra' => [
                    'oauth_credentials' => $customer,
                ],
            ],
        );

        return $response->toArray();
    }

    public function containers(): array
    {
        $response = $this->request(
            'GET',
            'api/containers',
        );

        return $response->toArray();
    }

    public function trade(OAuthCredentialsInterface $customer, $restaurantCode, int $withdrawQuantity, int $depositQuantity = 0): array
    {
        $items = [
            [
                'type' => 'WITHDRAW',
                'container_id' => 1, // TODO Make this dynamic
                'quantity' => $withdrawQuantity
            ]
        ];

        if ($depositQuantity > 0) {
            $items[] = [
                'type' => 'DEPOSIT',
                'container_id' => 1, // TODO Make this dynamic
                'quantity' => $depositQuantity,
            ];
        }

        try {

            $response = $this->request(
                'POST',
                'api/trades',
                [
                    'json' => [
                        'items' => $items,
                        'code_from_qr' => $restaurantCode,
                    ],
                    'extra' => [
                        'oauth_credentials' => $customer,
                    ],
                ]
            );

            return $response->toArray();

        } catch (ClientExceptionInterface $e) {

            $data = $e->getResponse()->toArray(false);

            throw new TradeException($data['message']);
        }
    }

    public function getOAuthAuthorizeUrl(array $params = [])
    {
        $baseUrl = $this->baseUrl;
        if (str_contains($this->baseUrl, 'host.docker.internal')) {
            $baseUrl = str_replace('host.docker.internal', 'localhost', $baseUrl);
        }

        $defaults = [
            'client_id' => $this->clientId,
            'response_type' => 'code',
            'redirect_uri' => $this->urlGenerator->generate('dabba_oauth_callback', [], UrlGeneratorInterface::ABSOLUTE_URL),
            'scope' => '*',
        ];

        $params = array_merge($defaults, $params);
        $queryString = http_build_query($params);

        return sprintf('%s/oauth2/authorize?%s', $baseUrl, $queryString);
    }

    public function authorizationCode($code)
    {
        // curl -v -X POST -d 'grant_type=authorization_code&client_secret=ClientSecret&client_id=ClientId&redirect_uri=http://localhost/cb&code=<INSERT_AUTH_CODE_HERE>' http://localhost/oauth2/token

        $response = $this->client->request(
            'POST',
            'oauth2/token',
            [
                'body' => [
                    'grant_type' => 'authorization_code',
                    'client_id' => $this->clientId,
                    'client_secret' => $this->clientSecret,
                    'redirect_uri' => $this->urlGenerator->generate('dabba_oauth_callback', [], UrlGeneratorInterface::ABSOLUTE_URL),
                    'code' => $code,
                ],
            ]
        );

        return $response->toArray();
    }

    public function createStateParamForOrder(OrderInterface $order)
    {
        return $this->jwtEncoder->encode([
            'exp' => (new \DateTime('+1 hour'))->getTimestamp(),
            'sub' => $this->iriConverter->getIriFromItem($order),
        ]);
    }
}
