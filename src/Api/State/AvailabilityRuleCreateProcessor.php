<?php

namespace AppBundle\Api\State;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProcessorInterface;
use AppBundle\Api\Dto\AvailabilityRuleInput;
use AppBundle\Entity\AvailabilityRule;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\SecurityBundle\Security;

final class AvailabilityRuleCreateProcessor implements ProcessorInterface
{
    public function __construct(
        private readonly EntityManagerInterface $entityManager,
        private readonly Security $security)
    {}

    /**
     * @param AvailabilityRuleInput $data
     */
    public function process($data, Operation $operation, array $uriVariables = [], array $context = []): AvailabilityRule
    {
        // Only a dispatcher may create a rule for someone else — a courier
        // submitting a "user" for another employee is silently overridden
        $targetUser = $this->security->isGranted('ROLE_DISPATCHER') && null !== $data->user
            ? $data->user
            : $this->security->getUser();

        $rule = new AvailabilityRule();
        $rule->setUser($targetUser);
        $rule->setType($data->type);
        $rule->setDayOfWeek($data->dayOfWeek);
        $rule->setStartTime(\DateTime::createFromFormat('H:i', $data->startTime));
        $rule->setEndTime(\DateTime::createFromFormat('H:i', $data->endTime));
        $rule->setComment($data->comment);

        $this->entityManager->persist($rule);
        $this->entityManager->flush();

        return $rule;
    }
}
