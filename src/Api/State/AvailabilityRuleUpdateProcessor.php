<?php

namespace AppBundle\Api\State;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProcessorInterface;
use AppBundle\Api\Dto\AvailabilityRuleUpdateInput;
use AppBundle\Entity\AvailabilityRule;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

final class AvailabilityRuleUpdateProcessor implements ProcessorInterface
{
    public function __construct(
        private readonly EntityManagerInterface $entityManager)
    {}

    /**
     * @param AvailabilityRuleUpdateInput $data
     */
    public function process($data, Operation $operation, array $uriVariables = [], array $context = []): AvailabilityRule
    {
        $rule = $this->entityManager->getRepository(AvailabilityRule::class)->find($uriVariables['id'] ?? 0);
        if (null === $rule) {
            throw new NotFoundHttpException('Availability rule not found');
        }

        $rule->setType($data->type);
        $rule->setDayOfWeek($data->dayOfWeek);
        $rule->setStartTime(\DateTime::createFromFormat('H:i', $data->startTime));
        $rule->setEndTime(\DateTime::createFromFormat('H:i', $data->endTime));
        $rule->setComment($data->comment);

        $this->entityManager->flush();

        return $rule;
    }
}
