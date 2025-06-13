<?php

namespace AppBundle\Security\OAuth\ResourceOwner;

use HWI\Bundle\OAuthBundle\OAuth\RequestDataStorageInterface;
use HWI\Bundle\OAuthBundle\OAuth\ResourceOwner\GenericOAuth2ResourceOwner;
use HWI\Bundle\OAuthBundle\OAuth\ResourceOwner\FacebookResourceOwner as BaseFacebookResourceOwner;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\Security\Http\HttpUtils;
use Symfony\Contracts\HttpClient\HttpClientInterface;

class FacebookResourceOwner extends GenericOAuth2ResourceOwner
{
    private $resourceOwner;
    private $proxyUri;
    private $enabled;

    public function __construct(
        HttpClientInterface $httpClient,
        HttpUtils $httpUtils,
        array $options,
        string $name,
        RequestDataStorageInterface $storage,
        string $proxyUri,
        bool $enabled)
    {
        $this->resourceOwner = new BaseFacebookResourceOwner(
            $httpClient, $httpUtils, $options, $name, $storage
        );

        $this->proxyUri = $proxyUri;
        $this->enabled = $enabled;

        parent::__construct($httpClient, $httpUtils, $options, $name, $storage);
    }

    /**
     * {@inheritdoc}
     */
    public function getUserInformation(array $accessToken, array $extraParameters = [])
    {
        return $this->resourceOwner->getUserInformation($accessToken, $extraParameters);
    }

    /**
     * {@inheritdoc}
     */
    public function getAuthorizationUrl($redirectUri, array $extraParameters = [])
    {
        return $this->resourceOwner->getAuthorizationUrl($redirectUri, $extraParameters);
    }

    /**
     * {@inheritdoc}
     */
    public function getAccessToken(Request $request, $redirectUri, array $extraParameters = [])
    {
        return $this->resourceOwner->getAccessToken($request, $redirectUri, $extraParameters = []);
    }

    /**
     * {@inheritdoc}
     */
    public function revokeToken($token)
    {
        return $this->resourceOwner->revokeToken($token);
    }

    /**
     * {@inheritdoc}
     */
    protected function configureOptions(OptionsResolver $resolver)
    {
        $configureOptions = new \ReflectionMethod(BaseFacebookResourceOwner::class, 'configureOptions');
        $configureOptions->setAccessible(true);
        $configureOptions->invoke($this->resourceOwner, $resolver);
    }

    /**
     * {@inheritdoc}
     */
    protected function doGetTokenRequest($url, array $parameters = [])
    {
        if ($this->enabled && isset($parameters['redirect_uri'])) {
            $parameters['redirect_uri'] = $this->proxyUri;
        }

        return parent::doGetTokenRequest($url, $parameters);
    }
}

