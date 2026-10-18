<?php

namespace AppBundle\Entity\Task;

/**
 * The version a client writes against, for the collections that are replaced
 * wholesale. Mapped per entity in Doctrine and exposed per entity in
 * `serialization.yaml`, since `TaskList` and `Tour` are read under different
 * groups.
 *
 * @see VersionedCollectionInterface
 */
trait VersionedCollectionTrait
{
    /**
     * Incremented on every change to the collection, including changes that
     * only touch its items.
     *
     * Defaulted here rather than left null because only `Tour` maps this as a
     * Doctrine version field; a `TaskList` would otherwise be inserted with no
     * version at all.
     *
     * @see \AppBundle\Doctrine\EventSubscriber\TaskCollectionVersionSubscriber
     */
    protected $version = 1;

    public function getVersion(): ?int
    {
        return $this->version;
    }

    public function bumpVersion(): void
    {
        $this->version = ((int) $this->version) + 1;
    }
}
