<?php

declare(strict_types=1);

namespace AppBundle\Business;

use AppBundle\Entity\Address;
use AppBundle\Entity\BusinessAccount;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\Security\Core\Security;

class Context
{
    /**
     * @var RequestStack
     */
    private RequestStack $requestStack;

    public const QUERY_PARAM_NAME = 'change_channel';

    public const COOKIE_KEY = 'channel_cart';

    public function __construct(RequestStack $requestStack, Security $security)
    {
        $this->requestStack = $requestStack;
        $this->security = $security;
    }

    public function isActive(): bool
    {
        if (null !== $request = $this->requestStack->getCurrentRequest()) {
            if ($request->query->has('_business')) {
                return $request->query->getBoolean('_business');
            }

            return '1' === $request->cookies->get('_coopcycle_business');
        }

        return false;
    }

    public function getBusinessAccount(): ?BusinessAccount
    {
        $user = $this->security->getUser();
        if ($user && $user->hasBusinessAccount()) {
            return $user->getBusinessAccount();
        }

        return null;
    }

    public function getShippingAddress(): ?Address
    {
        $businessAccount = $this->getBusinessAccount();
        if ($businessAccount) {
            return $businessAccount->getAddress();
        }

        return null;
    }
}
