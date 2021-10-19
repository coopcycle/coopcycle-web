<?php

namespace AppBundle\Security\Authentication\Token;

use Symfony\Component\Security\Core\Authentication\Token\AbstractToken;

class ApiKeyToken extends AbstractToken
{
    /**
     * @var string
     */
    protected $rawToken;

    /**
     * {@inheritdoc}
     */
    public function __construct(array $roles = [], $rawToken = null)
    {
        parent::__construct($roles);

        $this->rawToken = $rawToken;
        $this->setAuthenticated(true);
    }

    /**
     * {@inheritdoc}
     */
    public function getCredentials()
    {
        return $this->rawToken;
    }
}
