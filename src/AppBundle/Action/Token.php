<?php

namespace AppBundle\Action;

use Sensio\Bundle\FrameworkExtraBundle\Configuration\Method;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\HttpFoundation\JsonResponse;

class Token
{
    /**
     * @Route(
     *     name="token_check",
     *     path="/token/check",
     * )
     * @Method("GET")
     */
    public function checkAction()
    {
        return new JsonResponse('OK');
    }
}
