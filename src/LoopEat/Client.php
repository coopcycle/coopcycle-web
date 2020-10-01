<?php

namespace AppBundle\LoopEat;

use AppBundle\Entity\User;
use AppBundle\Entity\LocalBusiness;
use FOS\UserBundle\Model\UserManagerInterface;
use GuzzleHttp\Client as BaseClient;
use GuzzleHttp\Exception\RequestException;
use GuzzleHttp\HandlerStack;
use GuzzleHttp\Middleware;
use GuzzleHttp\Promise\RejectedPromise;
use GuzzleHttp\Psr7;
use Psr\Log\LoggerInterface;
use Psr\Log\NullLogger;
use Psr\Http\Message\RequestInterface;
use Psr\Http\Message\ResponseInterface;
use Symfony\Component\Security\Core\User\UserInterface;

class Client extends BaseClient
{
    const JWT_CLAIM_SUCCESS_REDIRECT = 'https://coopcycle.org/loopeat_success_redirect';
    const JWT_CLAIM_FAILURE_REDIRECT = 'https://coopcycle.org/loopeat_failure_redirect';

    private $logger;

    public function __construct(array $config = [], UserManagerInterface $userManager, LoggerInterface $logger)
    {
        $stack = HandlerStack::create();
        $stack->push($this->refreshToken());

        $config['handler'] = $stack;

        parent::__construct($config);

        $this->userManager = $userManager;
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

                                    $response = $this->request('POST', '/oauth/token', [
                                        'form_params' => $params,
                                    ]);

                                    $data = json_decode((string) $response->getBody(), true);

                                    $options['oauth_credentials']->setLoopeatAccessToken($data['access_token']);

                                    if ($options['oauth_credentials'] instanceof UserInterface) {
                                        $this->logger->info(sprintf('Saving new access token for "%s"',
                                            $options['oauth_credentials']->getUsername()));
                                        $this->userManager->updateUser($options['oauth_credentials']);
                                    }

                                    $request = Psr7\modify_request($request, [
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

        return sprintf('%s/oauth/authorize?%s', $this->getConfig('base_uri'), $queryString);
    }

    public function currentCustomer(User $user)
    {
        $response = $this->request('GET', '/customers/current', [
            'headers' => [
                'Authorization' => sprintf('Bearer %s', $user->getLoopeatAccessToken())
            ],
            'oauth_credentials' => $user,
        ]);

        return json_decode((string) $response->getBody(), true);
    }

    public function return(User $user, $quantity = 1)
    {
        $this->logger->info(sprintf('Returning %d Loopeats from "%s"',
            $quantity, $user->getUsername()));

        try {

            for ($i = 0; $i < $quantity; $i++) {

                $response = $this->request('GET', '/customers/return_loopeat', [
                    'headers' => [
                        'Authorization' => sprintf('Bearer %s', $user->getLoopeatAccessToken())
                    ],
                    'oauth_credentials' => $user,
                ]);

                $url = (string) $response->getBody();

                # returns the loopeats to the coopcycle's owner account
                $url = str_replace(
                    '/restaurants/return_loopeat',
                    '/partners/customer_return_loopeat',
                    $url);

                $this->logger->info(sprintf('Got token "%s" to return for "%s"', $url, $user->getUsername()));

                $response = $this->request('GET', $url, [
                    'auth' => [$this->loopEatPartnerId, $this->loopEatPartnerSecret]
                ]);
            }

        } catch (RequestException $e) {
            $this->logger->error($e->getMessage());
            return false;
        }

        $this->logger->info(sprintf('Successfully returned %d Loopeats from "%s"',
            $quantity, $user->getUsername()));

    }

    public function grab(User $user, LocalBusiness $restaurant, $quantity = 1)
    {
        $this->logger->info(sprintf('Grabbing %d Loopeats at "%s" for "%s"',
            $quantity, $restaurant->getName(), $user->getUsername()));

        try {

            for ($i = 0; $i < $quantity; $i++) {

                $response = $this->request('GET', '/customers/grab_loopeat', [
                    'headers' => [
                        'Authorization' => sprintf('Bearer %s', $user->getLoopeatAccessToken())
                    ],
                    'oauth_credentials' => $user,
                ]);

                $url = (string) $response->getBody();

                $this->logger->info(sprintf('Got token "%s" to grab for "%s"', $url, $user->getUsername()));

                $response = $this->request('GET', $url, [
                    'headers' => [
                        'Authorization' => sprintf('Bearer %s', $restaurant->getLoopeatAccessToken())
                    ],
                    'oauth_credentials' => $restaurant,
                ]);

            }

        } catch (RequestException $e) {
            $this->logger->error($e->getMessage());
            return false;
        }

        $this->logger->info(sprintf('Successfully grabbed %d Loopeats at "%s" for "%s"',
            $quantity, $restaurant->getName(), $user->getUsername()));

        return true;
    }
}
