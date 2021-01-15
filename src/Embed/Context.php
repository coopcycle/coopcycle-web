<?php

namespace AppBundle\Embed;

use Symfony\Component\HttpFoundation\Session\SessionInterface;

class Context
{
    public function __construct(SessionInterface $session)
    {
        $this->session = $session;
    }

    public function isEmbedded()
    {
        if (!$this->session->has('embed')) {
            return false;
        }

        return (bool) $this->session->get('embed');
    }

    public function isEnabled()
    {
        return $this->isEmbedded();
    }
}
