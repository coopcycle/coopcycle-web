<?php

namespace AppBundle\Api\Swagger;

use ApiPlatform\Core\OpenApi\Factory\OpenApiFactoryInterface;
use ApiPlatform\Core\OpenApi\OpenApi;
use ApiPlatform\Core\OpenApi\Model;

class SwaggerDecorator implements OpenApiFactoryInterface
{
    private $decorated;

    public function __construct(OpenApiFactoryInterface $decorated)
    {
        $this->decorated = $decorated;
    }

    public function __invoke(array $context = []): OpenApi
    {
        $openApi = $this->decorated->__invoke($context);

        /*
        unset($docs['paths']['/api/api_apps/{id}']);
        unset($docs['paths']['/api/opening_hours_specifications/{id}']);
        unset($docs['paths']['/api/task_events/{id}']);

        unset($docs['paths']['/api/remote_push_tokens/{id}']);
        unset($docs['paths']['/api/me/remote_push_tokens']);
        unset($docs['paths']['/api/me/remote_push_tokens/{token}']);

        unset($docs['paths']['/api/retail_prices/{id}']);
        */

        return $openApi;
    }
}
