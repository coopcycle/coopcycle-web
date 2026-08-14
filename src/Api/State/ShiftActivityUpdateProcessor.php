<?php

namespace AppBundle\Api\State;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProcessorInterface;
use AppBundle\Api\Dto\ShiftActivityUpdateInput;
use AppBundle\Entity\ShiftActivity;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

final class ShiftActivityUpdateProcessor implements ProcessorInterface
{
    public function __construct(
        private readonly EntityManagerInterface $entityManager)
    {}

    /**
     * @param ShiftActivityUpdateInput $data
     */
    public function process($data, Operation $operation, array $uriVariables = [], array $context = []): ShiftActivity
    {
        $activity = $this->entityManager->getRepository(ShiftActivity::class)->find($uriVariables['id'] ?? 0);
        if (null === $activity) {
            throw new NotFoundHttpException('Shift activity not found');
        }

        $activity->setLabel($data->label);
        $activity->setColor($data->color);

        $this->entityManager->flush();

        return $activity;
    }
}
