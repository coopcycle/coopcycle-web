<?php

declare(strict_types=1);

namespace AppBundle\Business;

use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\RequestStack;

class Context
{
    /**
     * @var RequestStack
     */
    private RequestStack $requestStack;

    public const QUERY_PARAM_NAME = 'change_channel';

    public const COOKIE_KEY = 'channel_cart';

    public function __construct(RequestStack $requestStack)
    {
        $this->requestStack = $requestStack;
    }

    public function isActive(): bool
    {
        $request = $this->requestStack->getMainRequest();

        if ($request->query->has('_business')) {
            return $request->query->getBoolean('_business');
        }

        return '1' === $request->cookies->get('_coopcycle_business');
    }
}
