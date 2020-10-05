<?php

namespace AppBundle\Security\OAuth\ResourceOwner;

use Http\Client\Common\HttpMethodsClient;
use HWI\Bundle\OAuthBundle\OAuth\RequestDataStorageInterface;
use HWI\Bundle\OAuthBundle\OAuth\ResourceOwner\FacebookResourceOwner as BaseFacebookResourceOwner;
use Symfony\Component\Security\Http\HttpUtils;

class FacebookResourceOwner extends BaseFacebookResourceOwner
{
    private $proxyUri;
    private $enabled;

    public function __construct(
        HttpMethodsClient $httpClient,
        HttpUtils $httpUtils,
        array $options,
        string $name,
        RequestDataStorageInterface $storage,
        string $proxyUri,
        bool $enabled)
    {
        parent::__construct($httpClient, $httpUtils, $options, $name, $storage);

        $this->proxyUri = $proxyUri;
        $this->enabled = $enabled;
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

