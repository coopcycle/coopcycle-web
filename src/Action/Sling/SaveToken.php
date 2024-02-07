<?php

namespace AppBundle\Action\Sling;

use AppBundle\Utils\SlingAPIServiceFactory;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\Session\SessionInterface;

class SaveToken {
    public function __construct(private SessionInterface $session) { }

    private function getToken(Request $request): ?string
    {
        $data = [];
        $content = $request->getContent();
        if (!empty($content)) {
            $data = json_decode($content, true);
        }

        if (isset($data['token'])) {
            return $data['token'];
        }

        return null;
    }

    public function __invoke(Request $request): Response {

        $this->session->set('sling.token', $this->getToken($request));
        $slingAPI = (new SlingAPIServiceFactory)->create($this->session);

        try {
            $slingAPI->fetchSession();
            return new Response('Accepted', 202);
        } catch (\Exception $e) {
            $this->session->remove('sling.token');
            return new Response('Invalid token', 400);
        }
    }
}
