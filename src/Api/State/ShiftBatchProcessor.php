<?php

namespace AppBundle\Api\State;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProcessorInterface;
use AppBundle\Api\Dto\ShiftBatchInput;
use AppBundle\Api\Resource\ShiftBatch;
use AppBundle\Entity\Shift;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;

final class ShiftBatchProcessor implements ProcessorInterface
{
    public function __construct(
        private readonly EntityManagerInterface $entityManager,
        private readonly Security $security)
    {}

    /**
     * @param ShiftBatchInput $data
     */
    public function process($data, Operation $operation, array $uriVariables = [], array $context = []): ShiftBatch
    {
        $user = $this->security->getUser();
        $created = 0;

        foreach ($data->shifts as $item) {
            if (empty($item['type']) || empty($item['startsAt']) || empty($item['endsAt'])) {
                throw new BadRequestHttpException('Each shift requires type, startsAt and endsAt');
            }

            $shift = new Shift();
            $shift->setType($item['type']);
            $shift->setStartsAt(new \DateTime($item['startsAt']));
            $shift->setEndsAt(new \DateTime($item['endsAt']));
            $shift->setSlots(max(1, (int) ($item['slots'] ?? 1)));
            $shift->setCreatedBy($user);

            $this->entityManager->persist($shift);
            $created++;
        }

        $this->entityManager->flush();

        return new ShiftBatch($created);
    }
}
