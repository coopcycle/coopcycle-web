<?php

namespace AppBundle\Api\State;

use ApiPlatform\Doctrine\Common\State\RemoveProcessor;
use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProcessorInterface;
use ApiPlatform\Validator\ValidatorInterface;

final class ValidationAwareRemoveProcessor implements ProcessorInterface
{
    public function __construct(
        private RemoveProcessor $removeProcessor,
        private ValidatorInterface $validator)
    {}

    public function process($data, Operation $operation, array $uriVariables = [], array $context = [])
    {
        $this->validator->validate($data, ['groups' => ['deleteValidation']]);
        $this->removeProcessor->process($data, $operation, $uriVariables, $context);
    }
}

