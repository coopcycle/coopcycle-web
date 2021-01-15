<?php

namespace AppBundle\Security\Authentication\Token;

use Lexik\Bundle\JWTAuthenticationBundle\Security\Authentication\Token\PreAuthenticationJWTUserToken;
use Symfony\Component\Security\Core\Authentication\Token\AbstractToken;

class CartSessionToken extends AbstractToken
{
    public $rawSessionToken;
    public $lexik;

    public function __construct($rawSessionToken, PreAuthenticationJWTUserToken $lexik = null)
    {
        $this->rawSessionToken = $rawSessionToken;
        $this->lexik = $lexik;

        parent::__construct();
    }

    public function getCredentials()
    {
        return '';
    }
}
