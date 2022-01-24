<?php

namespace AppBundle\Edenred;

use ApiPlatform\Core\Api\IriConverterInterface;
use AppBundle\Sylius\Customer\CustomerInterface;
use AppBundle\Sylius\Order\OrderInterface;
use GuzzleHttp\Client as GuzzleClient;
use GuzzleHttp\HandlerStack;
use Lexik\Bundle\JWTAuthenticationBundle\Encoder\JWTEncoderInterface;
use Psr\Log\LoggerInterface;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;

class Authentication
{
    public function __construct(
        string $clientId,
        string $clientSecret,
        RefreshTokenHandler $refreshTokenHandler,
        UrlGeneratorInterface $urlGenerator,
        JWTEncoderInterface $jwtEncoder,
        IriConverterInterface $iriConverter,
        LoggerInterface $logger,
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
        $this->urlGenerator = $urlGenerator;
        $this->jwtEncoder = $jwtEncoder;
        $this->iriConverter = $iriConverter;
        $this->logger = $logger;
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
     * @return array|bool
     */
    public function authorizationCode(string $code)
    {
        // https://documenter.getpostman.com/view/10405248/TVewaQQX
        $params = array(
            'client_id' => $this->clientId,
            'client_secret' => $this->clientSecret,
            'grant_type' => 'authorization_code',
            'code' => $code,
            'redirect_uri' => $this->urlGenerator->generate('edenred_oauth_callback', [], UrlGeneratorInterface::ABSOLUTE_URL),
        );

        $url = sprintf('%s/connect/token', $this->baseUrl);

        $ch = curl_init($url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query($params));

        $res = curl_exec($ch);

        $httpCode = !curl_errno($ch) ? curl_getinfo($ch, CURLINFO_HTTP_CODE) : null;

        if ($httpCode !== 200) {
            $data = json_decode($res, true);

            $this->logger->error(sprintf(
                'There was an "%s" error when trying to fetch an access token from Edenred: "%s"',
                $data['error'],
                $data['error_description'] ?? ''
            ));

            curl_close($ch);

            return false;
        }

        curl_close($ch);

        return json_decode($res, true);
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
