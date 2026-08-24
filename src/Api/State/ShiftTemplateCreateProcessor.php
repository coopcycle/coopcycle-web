<?php

namespace AppBundle\Api\State;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProcessorInterface;
use AppBundle\Api\Dto\ShiftTemplateCreateInput;
use AppBundle\Entity\ShiftTemplate;
use AppBundle\Entity\User;
use AppBundle\Service\ShiftManager;
use Symfony\Bundle\SecurityBundle\Security;

final class ShiftTemplateCreateProcessor implements ProcessorInterface
{
    public function __construct(
        private readonly ShiftManager $shiftManager,
        private readonly Security $security)
    {}

    /**
     * @param ShiftTemplateCreateInput $data
     */
    public function process($data, Operation $operation, array $uriVariables = [], array $context = []): ShiftTemplate
    {
        $monday = (new \DateTimeImmutable($data->week))->modify('monday this week');

        /** @var User $user */
        $user = $this->security->getUser();

        return $this->shiftManager->createTemplateFromWeek($data->name, $monday, $user);
    }
}
