<?php

namespace AppBundle\Api\State;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProcessorInterface;
use AppBundle\Api\Dto\ShiftActivityInput;
use AppBundle\Entity\ShiftActivity;
use AppBundle\Entity\ShiftActivityRepository;
use Cocur\Slugify\SlugifyInterface;
use Doctrine\ORM\EntityManagerInterface;

final class ShiftActivityCreateProcessor implements ProcessorInterface
{
    public function __construct(
        private readonly EntityManagerInterface $entityManager,
        private readonly SlugifyInterface $slugify)
    {}

    /**
     * @param ShiftActivityInput $data
     */
    public function process($data, Operation $operation, array $uriVariables = [], array $context = []): ShiftActivity
    {
        /** @var ShiftActivityRepository $repository */
        $repository = $this->entityManager->getRepository(ShiftActivity::class);

        $activity = new ShiftActivity();
        $activity->setLabel($data->label);
        $activity->setSlug($this->uniqueSlug($repository, $this->slugify->slugify($data->label)));

        $this->entityManager->persist($activity);
        $this->entityManager->flush();

        return $activity;
    }

    private function uniqueSlug(ShiftActivityRepository $repository, string $base): string
    {
        $slug = $base;
        $suffix = 2;

        while (null !== $repository->findOneBySlug($slug)) {
            $slug = sprintf('%s-%d', $base, $suffix++);
        }

        return $slug;
    }
}
