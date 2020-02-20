<?php

namespace AppBundle\LoopEat;

use AppBundle\Entity\ApiUser;
use AppBundle\Entity\Restaurant;
use GuzzleHttp\Client as BaseClient;
use GuzzleHttp\Exception\RequestException;
use GuzzleHttp\HandlerStack;
use GuzzleHttp\Middleware;
use GuzzleHttp\Promise\RejectedPromise;
use GuzzleHttp\Psr7;
use Psr\Http\Message\RequestInterface;
use Psr\Http\Message\ResponseInterface;

class Client extends BaseClient
{
    public function __construct(array $config = [])
    {
        $stack = HandlerStack::create();
        $stack->push($this->refreshToken());

        $config['handler'] = $stack;

        parent::__construct($config);
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
                        if (401 === $value->getStatusCode()) {

                            if (isset($options['oauth_credentials'])) {

                                $params = array(
                                    'grant_type' => 'refresh_token',
                                    'refresh_token' => $options['oauth_credentials']->getLoopeatRefreshToken(),
                                    'client_id' => $this->loopEatClientId,
                                    'client_secret' => $this->loopEatClientSecret,
                                );

                                // https://www.oauth.com/oauth2-servers/access-tokens/refreshing-access-tokens/
                                $response = $this->request('POST', '/oauth/token', [
                                    'form_params' => $params,
                                ]);

                                $data = json_decode((string) $response->getBody(), true);

                                $options['oauth_credentials']->setLoopeatAccessToken($data['access_token']);

                                // TODO Flush

                                $request = Psr7\modify_request($request, [
                                    'set_headers' => [
                                        'Authorization' => sprintf('Bearer %s', $data['access_token'])
                                    ]
                                ]);

                                return $handler($request, $options);
                            }
                        }

                        return $value;
                    }
                );
            };
        };
    }

    public function currentCustomer(ApiUser $customer)
    {
        $response = $this->request('GET', '/customers/current', [
            'headers' => [
                'Authorization' => sprintf('Bearer %s', $customer->getLoopeatAccessToken())
            ],
            'oauth_credentials' => $customer,
        ]);

        return json_decode((string) $response->getBody(), true);
    }

    public function grab(ApiUser $customer, Restaurant $restaurant, $quantity = 1)
    {
        for ($i = 0; $i < $quantity; $i++) {

            $response = $this->request('GET', '/customers/grab_loopeat', [
                'headers' => [
                    'Authorization' => sprintf('Bearer %s', $customer->getLoopeatAccessToken())
                ],
                'oauth_credentials' => $customer,
            ]);

            $url = (string) $response->getBody();

            // $parts = parse_url($url);
            // $params = parse_str($parts['query']);
            // print_r($params);

            $response = $this->request('GET', $url, [
                'headers' => [
                    'Authorization' => sprintf('Bearer %s', $restaurant->getLoopeatAccessToken())
                ],
                'oauth_credentials' => $restaurant,
            ]);

            if ($response->getStatusCode() !== 200) {
                return false;
            }
        }

        return true;
    }
}
