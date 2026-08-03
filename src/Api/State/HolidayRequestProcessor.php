<?php

namespace AppBundle\Api\State;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProcessorInterface;
use AppBundle\Entity\HolidayRequest;
use Symfony\Bundle\SecurityBundle\Security;

class HolidayRequestProcessor implements ProcessorInterface
{
    public function __construct(
        private readonly ProcessorInterface $persistProcessor,
        private readonly Security $security)
    {}

    /**
     * @param HolidayRequest $data
     */
    public function process($data, Operation $operation, array $uriVariables = [], array $context = [])
    {
        // The user & status can not be set by the client
        $data->setUser($this->security->getUser());
        $data->setStatus(HolidayRequest::STATUS_PENDING);

        return $this->persistProcessor->process($data, $operation, $uriVariables, $context);
    }
}
