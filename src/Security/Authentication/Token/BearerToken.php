<?php

namespace AppBundle\Security\Authentication\Token;

use Lexik\Bundle\JWTAuthenticationBundle\Security\Authentication\Token\PreAuthenticationJWTUserToken;
use Symfony\Component\Security\Core\Authentication\Token\AbstractToken;
use Trikoder\Bundle\OAuth2Bundle\Security\Authentication\Token\OAuth2Token;

class BearerToken extends AbstractToken
{
    public $lexik;
    public $trikoder;

    public function __construct(PreAuthenticationJWTUserToken $lexik, OAuth2Token $trikoder)
    {
        $this->lexik = $lexik;
        $this->trikoder = $trikoder;

        parent::__construct();
    }

    public function getCredentials()
    {
        return '';
    }
}
