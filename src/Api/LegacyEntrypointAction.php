<?php

namespace AppBundle\Api;

use ApiPlatform\Symfony\Action\EntrypointAction;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\JsonResponse;

class LegacyEntrypointAction
{
    public function __construct(private readonly EntrypointAction $decorated)
    {}

    /**
     * @return Response
     */
    public function __invoke(Request $request)
    {
        $response = call_user_func($this->decorated, $request);

        $data = json_decode($response->getContent(), true);

        $data['@context'] = '/api/contexts/Entrypoint';
        $data['@id'] = '/api';
        $data['@type'] = 'Entrypoint';

        return new JsonResponse($data);
    }
}
