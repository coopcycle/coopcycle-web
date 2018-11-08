<?php

namespace AppBundle\Action;

use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\HttpFoundation\JsonResponse;

class Token
{
    /**
     * @Route(
     *     name="token_check",
     *     path="/token/check",
     *     methods={"GET"}
     * )
     */
    public function checkAction()
    {
        return new JsonResponse('OK');
    }
}
