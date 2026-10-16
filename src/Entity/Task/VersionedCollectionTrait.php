<?php

namespace AppBundle\Entity\Task;

/**
 * The version a client writes against, for the collections that are replaced
 * wholesale. Mapped per entity in Doctrine (`<field name="version" ...
 * version="true"/>`) and exposed per entity in `serialization.yaml`, since
 * `TaskList` and `Tour` are read under different groups.
 *
 * @see VersionedCollectionInterface
 */
trait VersionedCollectionTrait
{
    /**
     * Incremented on every change to the collection, including changes that
     * only touch its items.
     *
     * @see \AppBundle\Doctrine\EventSubscriber\TaskCollectionVersionSubscriber
     */
    protected $version;

    public function getVersion(): ?int
    {
        return $this->version;
    }
}
