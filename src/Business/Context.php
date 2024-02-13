<?php

declare(strict_types=1);

namespace AppBundle\Business;

use AppBundle\Entity\BusinessAccount;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\Security\Core\Authentication\Token\Storage\TokenStorageInterface;

class Context
{
    /**
     * @var RequestStack
     */
    private RequestStack $requestStack;

    public const QUERY_PARAM_NAME = 'change_channel';

    public const COOKIE_KEY = 'channel_cart';

    public function __construct(RequestStack $requestStack, TokenStorageInterface $tokenStorage)
    {
        $this->requestStack = $requestStack;
        $this->tokenStorage = $tokenStorage;
    }

    public function isActive(): bool
    {
        $request = $this->requestStack->getMainRequest();

        if ($request->query->has('_business')) {
            return $request->query->getBoolean('_business');
        }

        return '1' === $request->cookies->get('_coopcycle_business');
    }

    private function getUser()
    {
        if (null === $token = $this->tokenStorage->getToken()) {
            return;
        }

        if (!is_object($user = $token->getUser())) {
            // e.g. anonymous authentication
            return;
        }

        return $user;
    }

    public function getBusinessAccount(): ?BusinessAccount
    {
        $user = $this->getUser();
        if ($user && $user->hasBusinessAccount()) {
            return $user->getBusinessAccount();
        }

        return null;
    }
}
