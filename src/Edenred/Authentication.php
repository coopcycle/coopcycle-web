<?php

namespace AppBundle\Edenred;

use ApiPlatform\Core\Api\IriConverterInterface;
use AppBundle\Sylius\Customer\CustomerInterface;
use AppBundle\Sylius\Order\OrderInterface;
use GuzzleHttp\Client as GuzzleClient;
use GuzzleHttp\HandlerStack;
use Lexik\Bundle\JWTAuthenticationBundle\Encoder\JWTEncoderInterface;
use Symfony\Contracts\HttpClient\Exception\HttpExceptionInterface;
use Symfony\Component\HttpClient\HttpClient;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;

class Authentication
{
    private $baseUrl;
    private $clientId;
    private $clientSecret;
    private $client;

    public function __construct(
        string $clientId,
        string $clientSecret,
        RefreshTokenHandler $refreshTokenHandler,
        private UrlGeneratorInterface $urlGenerator,
        private JWTEncoderInterface $jwtEncoder,
        private IriConverterInterface $iriConverter,
        array $config = []
    )
    {
        $stack = HandlerStack::create();
        $stack->push($refreshTokenHandler);

        $config['handler'] = $stack;

        $this->client = new GuzzleClient($config);

        $this->baseUrl = $config['base_uri'];
        $this->clientId = $clientId;
        $this->clientSecret = $clientSecret;
    }

    /**
     * @param CustomerInterface|OrderInterface $subject
     */
    public function getAuthorizeUrl($subject)
    {
        $redirectUri = $this->urlGenerator
            ->generate('edenred_oauth_callback', [], UrlGeneratorInterface::ABSOLUTE_URL);

        // Use a JWT as the "state" parameter
        $state = $this->jwtEncoder->encode([
            'exp' => (new \DateTime('+1 hour'))->getTimestamp(),
            // The "sub" (Subject) claim contains the IRI of a resource
            'sub' => $this->iriConverter->getIriFromItem($subject),
        ]);

        $queryString = http_build_query([
            'client_id' => $this->clientId,
            'response_type' => 'code',
            'redirect_uri' => $redirectUri,
            'scope' => 'openid edg-xp-mealdelivery-api offline_access',
            'nonce' => base64_encode(random_bytes(20)),
            'acr_values' => 'tenant:fr-ctrtku',
            'ui_locales' => 'fr-FR',
            'state' => $state,
        ]);

        // https://sso.sbx.edenred.io/connect/authorize?response_type=code&client_id=XXX&scope=openid%20edg-xp-mealdelivery-api%20offline_access&redirect_uri=https://demo.coopcycle.org/edenred/oauth/callback&state=abc123&nonce=456azerty&acr_values=tenant:fr-ctrtku&ui_locales=en-EN
        return sprintf('%s/connect/authorize?%s', $this->baseUrl, $queryString);
    }

    public function decodeState($state): array
    {
        return $this->jwtEncoder->decode($state);
    }

    public function getSubject(array $payload)
    {
        return $this->iriConverter->getItemFromIri($payload['sub']);
    }

    /**
     * @return array
     * @throws HttpExceptionInterface
     */
    public function authorizationCode(string $code, ?string $redirectUri = null)
    {
        $client = HttpClient::create([
            'base_uri' => $this->baseUrl
        ]);

        // https://documenter.getpostman.com/view/10405248/TVewaQQX
        $response = $client->request('POST', 'connect/token', [
            'body' => [
                'client_id' => $this->clientId,
                'client_secret' => $this->clientSecret,
                'grant_type' => 'authorization_code',
                'code' => $code,
                'redirect_uri' => $redirectUri !== null ? $redirectUri : $this->urlGenerator->generate('edenred_oauth_callback', [], UrlGeneratorInterface::ABSOLUTE_URL),
            ]
        ]);

        return $response->toArray();
    }

    public function userInfo(CustomerInterface $customer)
    {
        $response = $this->client->request('GET', '/connect/userinfo', [
            'headers' => [
                'Authorization' => sprintf('Bearer %s', $customer->getEdenredCredentials()->getAccessToken()),
            ],
            'oauth_credentials' => $customer->getEdenredCredentials(),
        ]);


        return json_decode((string) $response->getBody(), true);
    }
}
