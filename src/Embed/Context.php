<?php

namespace AppBundle\Embed;

use Symfony\Component\HttpFoundation\Session\SessionInterface;
use Symfony\Component\HttpFoundation\RequestStack;

class Context
{
    public function __construct(RequestStack $requestStack)
    {
        $this->requestStack = $requestStack;
    }

    public function isEmbedded()
    {
        $request = $this->requestStack->getCurrentRequest();

        return $request->query->has('embed')
            && ('' === $request->query->get('embed') || true === $request->query->getBoolean('embed'));
    }

    public function isEnabled()
    {
        return $this->isEmbedded();
    }
}
