<?php

namespace AppBundle\Api\State;

use ApiPlatform\Doctrine\Orm\State\ItemProvider;
use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProviderInterface;
use ApiPlatform\Validator\ValidatorInterface;

final class ValidateOrderProvider implements ProviderInterface
{
    public function __construct(
        private readonly ItemProvider $provider,
        private readonly ValidatorInterface $validator
        )
    {}

    public function provide(Operation $operation, array $uriVariables = [], array $context = []): object|array|null
    {
        $data = $this->provider->provide($operation, $uriVariables, $context);

        $this->validator->validate($data);

        return $data;
    }
}

