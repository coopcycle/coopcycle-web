<?php

namespace AppBundle\Api\Swagger;

use ApiPlatform\Core\OpenApi\Factory\OpenApiFactoryInterface;
use ApiPlatform\Core\OpenApi\OpenApi;
use ApiPlatform\Core\OpenApi\Model;

class SwaggerDecorator implements OpenApiFactoryInterface
{
    private $decorated;

    private static $excluded = [
        '/api/api_apps/{id}',
        '/api/opening_hours_specifications/{id}',
        '/api/task_events/{id}',
        '/api/remote_push_tokens/{id}',
        '/api/me/remote_push_tokens',
        '/api/me/remote_push_tokens/{token}',
        '/api/retail_prices/{id}',
        '/api/time_slot_choices/{id}',
        '/api/customers/{id}',
    ];

    public function __construct(OpenApiFactoryInterface $decorated)
    {
        $this->decorated = $decorated;
    }

    public function __invoke(array $context = []): OpenApi
    {
        $openApi = $this->decorated->__invoke($context);

        $paths = $openApi->getPaths()->getPaths();

        $filteredPaths = new Model\Paths();
        foreach ($paths as $path => $pathItem) {
            if (in_array($path, self::$excluded)) {
                continue;
            }
            $filteredPaths->addPath($path, $pathItem);
        }

        return $openApi->withPaths($filteredPaths);
    }
}
