<?php

namespace AppBundle\Action;

use Symfony\Component\HttpFoundation\InputBag;
use Symfony\Component\HttpFoundation\Request;

abstract class Base
{
    protected function parseRequest(Request $request): InputBag
    {
        $data = [];
        $content = $request->getContent();
        if (!empty($content)) {
            $data = json_decode($content, true);
        }

        return new InputBag($data);
    }
}
