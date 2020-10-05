<?php

namespace AppBundle\Twig;

use Lcobucci\JWT\Builder;
use Lcobucci\JWT\Signer\Key;
use Lcobucci\JWT\Signer\Hmac\Sha256;
use Twig\Extension\RuntimeExtensionInterface;

/**
 * @link https://github.com/hwi/HWIOAuthBundle/issues/1410#issuecomment-422769956
 */
class OAuthRuntime implements RuntimeExtensionInterface
{
    private $key;
    private $proxyUri;
    private $enabled;

    public function __construct(string $key, string $proxyUri, bool $enabled)
    {
        $this->key = $key;
        $this->proxyUri = $proxyUri;
        $this->enabled = $enabled;
    }

    public function modifyUrl(string $url)
    {
        if (!$this->enabled) {

            return $url;
        }

        $parts = parse_url($url);
        if (!is_array($parts)) {

            return $url;
        }

        parse_str($parts['query'], $params);

        $redirectUri = $params['redirect_uri'];
        unset($params['redirect_uri']);

        $time = time();
        $signer = new Sha256();

        $token = (new Builder())
            ->issuedBy($redirectUri)
            ->expiresAt($time + 3600)
            ->getToken($signer, new Key($this->key));

        $params = array_merge($params, [
            'redirect_uri' => $this->proxyUri,
            'state' => (string) $token,
        ]);

        return sprintf('%s://%s%s?%s',
            $parts['scheme'],
            $parts['host'],
            $parts['path'],
            http_build_query($params)
        );
    }
}
