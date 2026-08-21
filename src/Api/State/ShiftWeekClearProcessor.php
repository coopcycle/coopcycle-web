<?php

namespace AppBundle\Api\State;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProcessorInterface;
use AppBundle\Api\Dto\ShiftWeekClearInput;
use AppBundle\Api\Resource\ShiftWeekClear;
use AppBundle\Entity\SchedulePublication;
use AppBundle\Service\ShiftManager;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;

final class ShiftWeekClearProcessor implements ProcessorInterface
{
    public function __construct(
        private readonly EntityManagerInterface $entityManager,
        private readonly ShiftManager $shiftManager)
    {}

    /**
     * @param ShiftWeekClearInput $data
     */
    public function process($data, Operation $operation, array $uriVariables = [], array $context = []): ShiftWeekClear
    {
        $monday = (new \DateTimeImmutable($data->week))->modify('monday this week');

        $publication = $this->entityManager->getRepository(SchedulePublication::class)
            ->findOneByWeekStart($monday);
        if (null !== $publication) {
            throw new BadRequestHttpException('This week is already published and cannot be cleared');
        }

        $cleared = $this->shiftManager->clearWeek($monday);

        return new ShiftWeekClear($cleared);
    }
}
