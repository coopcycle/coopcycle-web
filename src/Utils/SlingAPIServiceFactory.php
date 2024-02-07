<?php

namespace AppBundle\Utils;

use Symfony\Component\HttpFoundation\Session\SessionInterface;

class SlingAPIServiceFactory {
    public function create(SessionInterface $session): SlingAPIService {
        $token = $session->get('sling.token', null);

        if (is_null($token)) {
            throw new \Exception('Sling token not found in session');
        }

        return new SlingAPIService($token);
    }
}
