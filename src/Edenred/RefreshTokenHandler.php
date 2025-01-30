<?php

namespace AppBundle\Edenred;

use Doctrine\ORM\EntityManagerInterface;
use GuzzleHttp\Client;
use GuzzleHttp\Exception\BadResponseException;
use GuzzleHttp\Exception\RequestException;
use GuzzleHttp\Psr7\Utils;
use Psr\Http\Message\RequestInterface;
use Psr\Log\LoggerInterface;
use Psr\Log\NullLogger;

class RefreshTokenHandler
{
    private $baseUrl;
    private $clientId;
    private $clientSecret;
    private $entityManager;
    private $logger;

    public function __construct(
        string $baseUrl,
        string $clientId,
        string $clientSecret,
        EntityManagerInterface $entityManager,
        LoggerInterface $logger = null)
    {
        $this->baseUrl = $baseUrl;
        $this->clientId = $clientId;
        $this->clientSecret = $clientSecret;
        $this->entityManager = $entityManager;
        $this->logger = $logger ?? new NullLogger();
    }

    public function __invoke(callable $handler)
    {
        return function (RequestInterface $request, array $options) use ($handler) {
            return $handler($request, $options)->then(
                function ($value) use ($handler, $request, $options) {

                    $this->logger->info(sprintf('Request "%s %s" returned %d',
                        $request->getMethod(), $request->getUri(), $value->getStatusCode()));

                    if (401 === $value->getStatusCode()) {

                        if (isset($options['oauth_credentials'])) {

                            $client = new Client([
                                'base_uri' => $this->baseUrl,
                            ]);

                            // https://documenter.getpostman.com/view/10405248/TVewaQQX#d0525729-e8d6-41c6-b5be-62b1e43d9f21
                            $params = array(
                                'client_id' => $this->clientId,
                                'client_secret' => $this->clientSecret,
                                'grant_type' => 'refresh_token',
                                'refresh_token' => $options['oauth_credentials']->getRefreshToken(),
                                'scope' => 'openid xp-mealdelivery-api offline_access',
                            );

                            // https://www.oauth.com/oauth2-servers/access-tokens/refreshing-access-tokens/
                            try {

                                $this->logger->info(sprintf('Refreshing token with "%s"',
                                    $options['oauth_credentials']->getRefreshToken()));

                                $response = $client->request('POST', '/connect/token', [
                                    'form_params' => $params,
                                ]);

                                $data = json_decode((string) $response->getBody(), true);

                                $options['oauth_credentials']->setAccessToken($data['access_token']);
                                $options['oauth_credentials']->setRefreshToken($data['refresh_token']);

                                $this->entityManager->flush();

                                $request = Utils::modifyRequest($request, [
                                    'set_headers' => [
                                        'Authorization' => sprintf('Bearer %s', $data['access_token'])
                                    ]
                                ]);

                                return $handler($request, $options);

                            } catch (BadResponseException $e) {

                                $this->logger->error('Refresh token has expired, clearing credentials');

                                $this->entityManager->remove($options['oauth_credentials']);
                                $this->entityManager->flush();

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
    }
}
