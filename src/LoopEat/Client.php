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
use Symfony\Contracts\Cache\CacheInterface;
use Symfony\Contracts\Cache\ItemInterface;
use Webmozart\Assert\Assert;

/**
 * @see https://collectif-impec-api-preprod.herokuapp.com/api-docs/index.html
 */
class Client
{
    const JWT_CLAIM_SUCCESS_REDIRECT = 'https://coopcycle.org/loopeat_success_redirect';
    const JWT_CLAIM_FAILURE_REDIRECT = 'https://coopcycle.org/loopeat_failure_redirect';

    private $client;

    public function __construct(
        private EntityManagerInterface $objectManager,
        private JWTEncoderInterface $jwtEncoder,
        private IriConverterInterface $iriConverter,
        private UrlGeneratorInterface $urlGenerator,
        private CacheInterface $projectCache,
        private LoggerInterface $logger,
        array $config = [])
    {
        if (isset($config['handler']) && $config['handler'] instanceof HandlerStack) {
            $stack = $config['handler'];
        } else {
            $stack = HandlerStack::create();
            $stack->push($this->refreshToken());
        }

        $config['handler'] = $stack;

        $this->client = new GuzzleClient($config);

        $this->objectManager = $objectManager;
        $this->jwtEncoder = $jwtEncoder;
        $this->iriConverter = $iriConverter;
        $this->urlGenerator = $urlGenerator;
        $this->projectCache = $projectCache;
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
                                    'redirect_uri' => $this->urlGenerator->generate('loopeat_oauth_callback', [], UrlGeneratorInterface::ABSOLUTE_URL),
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

                                    if (isset($data['refresh_token'])
                                        && $data['refresh_token'] !== $options['oauth_credentials']->getLoopeatRefreshToken()) {
                                        $options['oauth_credentials']->setLoopeatRefreshToken($data['refresh_token']);
                                    }

                                    $this->objectManager->flush();

                                    $request = Utils::modifyRequest($request, [
                                        'set_headers' => [
                                            'Authorization' => sprintf('Bearer %s', $data['access_token'])
                                        ]
                                    ]);

                                    return $handler($request, $options);

                                } catch (RequestException $e) {

                                    $this->logger->error($e->getMessage());

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
            'redirect_uri' => $this->urlGenerator->generate('loopeat_oauth_callback', [], UrlGeneratorInterface::ABSOLUTE_URL),
        ];

        $params = array_merge($defaults, $params);
        $queryString = http_build_query($params);

        $initiative = $this->initiative();

        return sprintf('%s?%s', $initiative['customer_authorization_url'], $queryString);
    }

    public function currentCustomer(OAuthCredentialsInterface $credentials)
    {
        $response = $this->client->request('GET', '/api/v1/partners/customer/current', [
            'headers' => [
                'Authorization' => sprintf('Bearer %s', $credentials->getLoopeatAccessToken())
            ],
            'oauth_credentials' => $credentials,
        ]);

        $res = json_decode((string) $response->getBody(), true);

        return $res['data'];
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

    public function createStateParamForOrder(OrderInterface $order, $useDeepLink = false)
    {
        return $this->jwtEncoder->encode([
            'exp' => (new \DateTime('+1 hour'))->getTimestamp(),
            'sub' => $this->iriConverter->getIriFromItem($order),
            // Custom claims
            self::JWT_CLAIM_SUCCESS_REDIRECT =>
                $useDeepLink ? 'coopcycle://loopeat_oauth_redirect' : $this->urlGenerator->generate('loopeat_success', [], UrlGeneratorInterface::ABSOLUTE_URL),
            self::JWT_CLAIM_FAILURE_REDIRECT =>
                $useDeepLink ? 'coopcycle://loopeat_oauth_redirect' : $this->urlGenerator->generate('loopeat_failure', [], UrlGeneratorInterface::ABSOLUTE_URL),
        ]);
    }

    public function getRestaurantOAuthAuthorizeUrl($params)
    {
        $defaults = [
            'client_id' => $this->loopEatClientId,
            'response_type' => 'code',
            'scope' => 'read write partner:manage user_account:read',
        ];

        $params = array_merge($defaults, $params);
        $queryString = http_build_query($params);

        $initiative = $this->initiative();

        return sprintf('%s?%s', $initiative['restaurant_authorization_url'], $queryString);
    }

    private function getPartnerToken()
    {
        return base64_encode(sprintf('%s:%s', $this->loopEatClientId, $this->loopEatClientSecret));
    }

    public function currentRestaurantOwner(LocalBusiness $restaurant)
    {
        $response = $this->client->request('GET', '/api/v1/partners/restaurant_owner/current', [
            'headers' => [
                'Authorization' => sprintf('Bearer %s', $restaurant->getLoopeatAccessToken())
            ],
            'oauth_credentials' => $restaurant,
        ]);

        $res = json_decode((string) $response->getBody(), true);

        return $res['data'];
    }

    public function currentRestaurant(LocalBusiness $restaurant)
    {
        $response = $this->client->request('GET', '/api/v1/partners/restaurants/current', [
            'headers' => [
                'Authorization' => sprintf('Bearer %s', $restaurant->getLoopeatAccessToken())
            ],
            'oauth_credentials' => $restaurant,
        ]);

        $res = json_decode((string) $response->getBody(), true);

        return $res['data'];
    }

    public function getFormats(LocalBusiness $restaurant): array
    {
        try {

            $currentRestaurant = $this->currentRestaurant($restaurant);

            $response = $this->client->request('GET', sprintf('/api/v1/partners/restaurants/%s/formats', $currentRestaurant['id']), [
                'headers' => [
                    'Authorization' => sprintf('Basic %s', $this->getPartnerToken())
                ],
            ]);

            $res = json_decode((string) $response->getBody(), true);

            return $res['data'];

        } catch (RequestException $e) {
            $this->logger->error($e->getMessage());
        }

        return [];
    }

    public function initiative()
    {
        return $this->projectCache->get('loopeat.initiative', function (ItemInterface $item) {

            $item->expiresAfter(60 * 60 * 24);

            $response = $this->client->request('GET', '/api/v1/partners/initiatives', [
                'headers' => [
                    'Authorization' => sprintf('Basic %s', $this->getPartnerToken())
                ],
            ]);

            $res = json_decode((string) $response->getBody(), true);

            $initiatives = $res['data'];

            return current($initiatives);
        });
    }

    public function createOrder(OrderInterface $order)
    {
        $deliver = array_map(function ($format) {
            return [
                ...$format,
                'act' => 'deliver',
            ];
        }, $order->getFormatsToDeliverForLoopeat());

        $pickup = array_map(function ($format) {
            return [
                ...$format,
                'act' => 'pickup',
            ];
        }, $order->getLoopeatReturns());

        $formats = [ ...$deliver, ...$pickup ];

        try {

            $restaurant = $order->getRestaurant();

            $currentRestaurant = $this->currentRestaurant($restaurant);

            // Assert::isInstanceOf($order->getCustomer(), CustomerInterface::class);
            Assert::isInstanceOf($order->getCustomer(), OAuthCredentialsInterface::class);

            $response = $this->client->request('POST', sprintf('/api/v1/partners/restaurants/%s/orders', $currentRestaurant['id']), [
                'headers' => [
                    'Authorization' => sprintf('Bearer %s', $order->getCustomer()->getLoopeatAccessToken())
                ],
                'oauth_credentials' => $order->getCustomer(),
                'json' => [
                    'order' => [
                        'external_id' => $order->getId(),
                        'formats' => $formats,
                    ]
                ],
            ]);

            $res = json_decode((string) $response->getBody(), true);

            return $res['data'];

        } catch (RequestException $e) {
            $this->logger->error($e->getMessage());
            return false;
        }
    }

    public function validateOrder(OrderInterface $order)
    {
        try {

            $this->logger->info(sprintf('Validating order "%s", with id "%s"', $order->getNumber(), $order->getLoopeatOrderId()));

            $response = $this->client->request('POST', sprintf('/api/v1/partners/orders/%s/validate', $order->getLoopeatOrderId()), [
                'headers' => [
                    'Authorization' => sprintf('Bearer %s', $order->getRestaurant()->getLoopeatAccessToken())
                ],
                'oauth_credentials' => $order->getRestaurant(),
                'json' => [],
            ]);

            $res = json_decode((string) $response->getBody(), true);

            return $res['data'];

        } catch (RequestException $e) {
            $this->logger->error($e->getMessage());
            return false;
        }
    }

    public function finishOrder(OrderInterface $order)
    {
        try {

            $this->logger->info(sprintf('Finishing order "%s", with id "%s"', $order->getNumber(), $order->getLoopeatOrderId()));

            $response = $this->client->request('POST', sprintf('/api/v1/partners/orders/%s/finish', $order->getLoopeatOrderId()), [
                'headers' => [
                    'Authorization' => sprintf('Basic %s', $this->getPartnerToken())
                ],
                'json' => [],
            ]);

            $res = json_decode((string) $response->getBody(), true);

            return $res['data'];

        } catch (RequestException $e) {
            $this->logger->error($e->getMessage());
            return false;
        }
    }

    public function listContainers(OAuthCredentialsInterface $customer)
    {
        try {

            $response = $this->client->request('GET', '/api/v1/partners/customer/containers', [
                'headers' => [
                    'Authorization' => sprintf('Bearer %s', $customer->getLoopeatAccessToken())
                ],
                'oauth_credentials' => $customer,
            ]);

            $res = json_decode((string) $response->getBody(), true);

            $containers = $res['data'];
            $containers = array_filter($containers, fn ($container) => $container['quantity'] > 0);

            return array_values($containers);

        } catch (RequestException $e) {
            $this->logger->error($e->getMessage());
            return false;
        }
    }

    public function updateDeliverFormats(OrderInterface $order)
    {
        $this->logger->info(sprintf('Updating formats for order "%s", with id "%s"', $order->getNumber(), $order->getLoopeatOrderId()));

        $response = $this->client->request('GET', sprintf('/api/v1/partners/orders/%s/formats', $order->getLoopeatOrderId()), [
            'headers' => [
                'Authorization' => sprintf('Basic %s', $this->getPartnerToken())
            ],
        ]);

        $res = json_decode((string) $response->getBody(), true);

        $orderFormats = $res['data'];

        $getOrderFormatId = function($formatId) use ($orderFormats) {
            foreach ($orderFormats as $orderFormat) {
                if ($orderFormat['act'] === 'deliver' && $orderFormat['details']['id'] === $formatId) {
                    return $orderFormat['id'];
                }
            }
        };

        foreach ($order->getLoopeatDeliver() as $itemId => $formats) {

            foreach ($formats as $format) {

                try {

                    $this->logger->info(sprintf('Updating formats for order "%s", setting format "%s" quantity to "%s"',
                        $order->getNumber(), $format['format_id'], $format['quantity']));

                    $restaurant = $order->getRestaurant();

                    $url = sprintf('/api/v1/partners/orders/%s/formats/%s', $order->getLoopeatOrderId(), $getOrderFormatId($format['format_id']));

                    $response = $this->client->request('PATCH', $url, [
                        'headers' => [
                            'Authorization' => sprintf('Bearer %s', $restaurant->getLoopeatAccessToken())
                        ],
                        'oauth_credentials' => $restaurant,
                        'json' => [
                            'order_format' => [
                                'quantity' => $format['quantity'],
                            ]
                        ],
                    ]);

                    $res = json_decode((string) $response->getBody(), true);

                } catch (RequestException $e) {
                    $this->logger->error($e->getMessage());
                }
            }
        }
    }

    public function getRestaurantContainers(OrderInterface $order)
    {
        try {

            $restaurant = $order->getRestaurant();

            $currentRestaurant = $this->currentRestaurant($restaurant);

            $response = $this->client->request('GET', sprintf('/api/v1/partners/restaurants/%s/containers', $currentRestaurant['id']), [
                'headers' => [
                    'Authorization' => sprintf('Basic %s', $this->getPartnerToken())
                ],
            ]);

            $res = json_decode((string) $response->getBody(), true);

            return $res['data'];

        } catch (RequestException $e) {
            $this->logger->error($e->getMessage());
            return false;
        }
    }
}
