<?php

namespace AppBundle\Embed;

use Symfony\Component\HttpFoundation\Session\SessionInterface;
use Symfony\Component\HttpFoundation\RequestStack;

class Context
{
    public function __construct(private RequestStack $requestStack)
    {
        $this->requestStack = $requestStack;
    }

    public function isEmbedded()
    {
        $request = $this->requestStack->getCurrentRequest();

        if ($request->attributes->has('_route')) {
            if ($request->attributes->get('_route') == 'public_share_order') {
                return true;
            }
        }

        return $request->query->has('embed')
            && ('' === $request->query->get('embed') || true === $request->query->getBoolean('embed'));
    }

    public function isEnabled()
    {
        return $this->isEmbedded();
    }
}
