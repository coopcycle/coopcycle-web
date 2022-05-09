<?php

namespace AppBundle\LoopEat;

use ApiPlatform\Core\Api\IriConverterInterface;
use AppBundle\Entity\LocalBusiness;
use AppBundle\Entity\Sylius\Customer;
use AppBundle\LoopEat\OAuthCredentialsInterface;
use AppBundle\Sylius\Order\OrderInterface;
use Doctrine\ORM\EntityManagerInterface;
use GuzzleHttp\Client as GuzzleClient;
use GuzzleHttp\Exception\RequestException;
use GuzzleHttp\HandlerStack;
use GuzzleHttp\Middleware;
use GuzzleHttp\Promise\RejectedPromise;
use GuzzleHttp\Psr7\Utils;
use Lexik\Bundle\JWTAuthenticationBundle\Encoder\JWTEncoderInterface;
use Psr\Log\LoggerInterface;
use Psr\Log\NullLogger;
use Psr\Http\Message\RequestInterface;
use Psr\Http\Message\ResponseInterface;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;

class Client
{
    const JWT_CLAIM_SUCCESS_REDIRECT = 'https://coopcycle.org/loopeat_success_redirect';
    const JWT_CLAIM_FAILURE_REDIRECT = 'https://coopcycle.org/loopeat_failure_redirect';

    private $logger;

    public function __construct(
        EntityManagerInterface $objectManager,
        JWTEncoderInterface $jwtEncoder,
        IriConverterInterface $iriConverter,
        UrlGeneratorInterface $urlGenerator,
        LoggerInterface $logger,
        array $config = [])
    {
        $stack = HandlerStack::create();
        $stack->push($this->refreshToken());

        $config['handler'] = $stack;

        $this->client = new GuzzleClient($config);

        $this->objectManager = $objectManager;
        $this->jwtEncoder = $jwtEncoder;
        $this->iriConverter = $iriConverter;
        $this->urlGenerator = $urlGenerator;
        $this->logger = $logger;
    }

    public function setLoopEatClientId($loopEatClientId)
    {
        $this->loopEatClientId = $loopEatClientId;
    }

    public function setLoopEatClientSecret($loopEatClientSecret)
    {
        $this->loopEatClientSecret = $loopEatClientSecret;
    }

    public function setLoopEatPartnerId($loopEatPartnerId)
    {
        $this->loopEatPartnerId = $loopEatPartnerId;
    }

    public function setLoopEatPartnerSecret($loopEatPartnerSecret)
    {
        $this->loopEatPartnerSecret = $loopEatPartnerSecret;
    }

    public function refreshToken()
    {
        return function (callable $handler) {
            return function (RequestInterface $request, array $options) use ($handler) {
                return $handler($request, $options)->then(
                    function ($value) use ($handler, $request, $options) {

                        $this->logger->info(sprintf('Request "%s %s" returned %d',
                            $request->getMethod(), $request->getUri(), $value->getStatusCode()));

                        if (401 === $value->getStatusCode()) {

                            if (isset($options['oauth_credentials'])) {

                                $params = array(
                                    'grant_type' => 'refresh_token',
                                    'refresh_token' => $options['oauth_credentials']->getLoopeatRefreshToken(),
                                    'client_id' => $this->loopEatClientId,
                                    'client_secret' => $this->loopEatClientSecret,
                                );

                                // https://www.oauth.com/oauth2-servers/access-tokens/refreshing-access-tokens/
                                try {

                                    $this->logger->info(sprintf('Refreshing token with "%s"',
                                        $options['oauth_credentials']->getLoopeatRefreshToken()));

                                    $response = $this->client->request('POST', '/oauth/token', [
                                        'form_params' => $params,
                                    ]);

                                    $data = json_decode((string) $response->getBody(), true);

                                    $options['oauth_credentials']->setLoopeatAccessToken($data['access_token']);

                                    if ($options['oauth_credentials'] instanceof Customer) {
                                        $this->logger->info(sprintf('Saving new access token for customer "%s"',
                                            $options['oauth_credentials']->getEmailCanonical()));
                                    }
                                    if ($options['oauth_credentials'] instanceof LocalBusiness) {
                                        $this->logger->info(sprintf('Saving new access token for restaurant "%s"',
                                            $options['oauth_credentials']->getName()));
                                    }

                                    $this->objectManager->flush();

                                    $request = Utils::modifyRequest($request, [
                                        'set_headers' => [
                                            'Authorization' => sprintf('Bearer %s', $data['access_token'])
                                        ]
                                    ]);

                                    return $handler($request, $options);

                                } catch (RequestException $e) {
                                    return $handler($request, $options);
                                }

                            }
                        }

                        return $value;
                    }
                );
            };
        };
    }

    public function getOAuthAuthorizeUrl(array $params = [])
    {
        $defaults = [
            'client_id' => $this->loopEatClientId,
            'response_type' => 'code',
        ];

        $params = array_merge($defaults, $params);
        $queryString = http_build_query($params);

        return sprintf('%s/oauth/authorize?%s', $this->client->getConfig('base_uri'), $queryString);
    }

    public function currentCustomer(OAuthCredentialsInterface $credentials)
    {
        $response = $this->client->request('GET', '/customers/current', [
            'headers' => [
                'Authorization' => sprintf('Bearer %s', $credentials->getLoopeatAccessToken())
            ],
            'oauth_credentials' => $credentials,
        ]);

        return json_decode((string) $response->getBody(), true);
    }

    public function return(Customer $customer, $quantity = 1): bool
    {
        $this->logger->info(sprintf('Returning %d Loopeats from "%s"',
            $quantity, $customer->getEmailCanonical()));

        if ($quantity < 1) {

            return true;
        }

        try {

            $response = $this->client->request('GET', '/customers/return_loopeat?amount='.$quantity, [
                'headers' => [
                    'Authorization' => sprintf('Bearer %s', $customer->getLoopeatAccessToken())
                ],
                'oauth_credentials' => $customer,
            ]);

            $url = (string) $response->getBody();

            # returns the loopeats to the coopcycle's owner account
            $url = str_replace(
                '/restaurants/return_loopeat',
                '/partners/customer_return_loopeat',
                $url);

            $this->logger->info(sprintf('Got token "%s" to return for "%s"', $url, $customer->getEmailCanonical()));

            $response = $this->client->request('GET', $url, [
                'auth' => [$this->loopEatPartnerId, $this->loopEatPartnerSecret]
            ]);

        } catch (RequestException $e) {
            $this->logger->error($e->getMessage());
            return false;
        }

        $this->logger->info(sprintf('Successfully returned %d Loopeats from "%s"',
            $quantity, $customer->getEmailCanonical()));

        return true;
    }

    public function grab(Customer $customer, LocalBusiness $restaurant, $quantity = 1): bool
    {
        $this->logger->info(sprintf('Grabbing %d Loopeats at "%s" for "%s"',
            $quantity, $restaurant->getName(), $customer->getEmailCanonical()));

        if ($quantity < 1) {

            return true;
        }

        try {

            $response = $this->client->request('GET', '/customers/grab_loopeat?amount='.$quantity, [
                'headers' => [
                    'Authorization' => sprintf('Bearer %s', $customer->getLoopeatAccessToken())
                ],
                'oauth_credentials' => $customer,
            ]);

            $url = (string) $response->getBody();

            $this->logger->info(sprintf('Got token "%s" to grab for "%s"', $url, $customer->getEmailCanonical()));

            $response = $this->client->request('GET', $url, [
                'headers' => [
                    'Authorization' => sprintf('Bearer %s', $restaurant->getLoopeatAccessToken())
                ],
                'oauth_credentials' => $restaurant,
            ]);

        } catch (RequestException $e) {
            $this->logger->error($e->getMessage());
            return false;
        }

        $this->logger->info(sprintf('Successfully grabbed %d Loopeats at "%s" for "%s"',
            $quantity, $restaurant->getName(), $customer->getEmailCanonical()));

        return true;
    }

    public function createStateParamForCustomer(Customer $customer)
    {
        return $this->jwtEncoder->encode([
            'exp' => (new \DateTime('+1 hour'))->getTimestamp(),
            'sub' => $this->iriConverter->getIriFromItem($customer),
            // Custom claims
            self::JWT_CLAIM_SUCCESS_REDIRECT =>
                $this->urlGenerator->generate('loopeat_success', [], UrlGeneratorInterface::ABSOLUTE_URL),
            self::JWT_CLAIM_FAILURE_REDIRECT =>
                $this->urlGenerator->generate('loopeat_failure', [], UrlGeneratorInterface::ABSOLUTE_URL),
        ]);
    }

    public function createStateParamForOrder(OrderInterface $order)
    {
        return $this->jwtEncoder->encode([
            'exp' => (new \DateTime('+1 hour'))->getTimestamp(),
            'sub' => $this->iriConverter->getIriFromItem($order),
            // Custom claims
            self::JWT_CLAIM_SUCCESS_REDIRECT =>
                $this->urlGenerator->generate('loopeat_success', [], UrlGeneratorInterface::ABSOLUTE_URL),
            self::JWT_CLAIM_FAILURE_REDIRECT =>
                $this->urlGenerator->generate('loopeat_failure', [], UrlGeneratorInterface::ABSOLUTE_URL),
        ]);
    }
}
