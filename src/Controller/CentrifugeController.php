<?php

namespace AppBundle\Controller;

use phpcent\Client as CentrifugoClient;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;

class CentrifugeController extends AbstractController
{
    /**
     * @see https://centrifugal.github.io/centrifugo/server/connection_expiration/
     *
     * @Route("/centrifuge/refresh", name="centrifuge_refresh", methods={"POST"})
     */
    public function refreshAction(Request $request, CentrifugoClient $centrifugoClient)
    {
        $user = $this->getUser();

        if (!$user) {
            return new Response('', 403);
        }

        return new JsonResponse([
            'token' => $centrifugoClient->generateConnectionToken($user->getUsername(), (time() + 3600)),
        ]);
    }
}
