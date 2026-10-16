<?php

namespace AppBundle\Entity\Task;

/**
 * A task collection whose contents a client may write back wholesale, and
 * which therefore carries a version to write against.
 *
 * `TaskList` and `Tour` are replaced as a whole by their write endpoints:
 * every task the payload omits is dropped. That makes a write built from a
 * stale read destructive rather than merely out of date, so clients echo the
 * version back as `If-Match` and the server refuses the ones that have fallen
 * behind.
 *
 * `Delivery` is the other collection under `TaskCollection` and deliberately
 * does not implement this: versioning it would put optimistic locking on every
 * concurrent delivery write, which is a far larger change than the dispatch
 * endpoints need.
 *
 * @see https://github.com/coopcycle/coopcycle-web/issues/5372
 */
interface VersionedCollectionInterface
{
    public function getVersion(): ?int;

    /**
     * Declared here because marking the collection dirty is how its version is
     * made to move when only its items changed.
     *
     * @see \AppBundle\Doctrine\EventSubscriber\TaskCollectionVersionSubscriber
     */
    public function setUpdatedAt(\DateTime $updatedAt);
}
