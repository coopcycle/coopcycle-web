<?php

namespace AppBundle\Entity;

use Doctrine\ORM\EntityRepository;

class ShiftActivityRepository extends EntityRepository
{
    public function findOneBySlug(string $slug): ?ShiftActivity
    {
        return $this->findOneBy(['slug' => $slug]);
    }

    /**
     * @return string[] slugs of the activities configured to be added to
     *                   the dispatch (see ShiftManager::addWeekToDispatch())
     */
    public function findSlugsAddedToDispatch(): array
    {
        return array_map(
            fn (ShiftActivity $activity) => $activity->getSlug(),
            $this->findBy(['addToDispatch' => true])
        );
    }
}
