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
 * The two carry it differently, on purpose:
 *
 *  - `Tour` maps it as a Doctrine version field. Optimistic locking is armed,
 *    `TourProcessor` enforces `If-Match`, and a concurrent write loses at the
 *    UPDATE.
 *  - `TaskList` maps it as a plain column that this bundle increments itself.
 *    Nothing sends `If-Match` for a task list yet, so arming Doctrine's
 *    locking there would buy no protection while making every concurrent
 *    `set_items` write — the busiest path in the product, and one every
 *    shipped version of the mobile app uses — fail. It becomes a real version
 *    field in step 2, together with the endpoints and the client handling.
 *
 * `Delivery` is the other collection under `TaskCollection` and deliberately
 * has no version at all.
 *
 * @see https://github.com/coopcycle/coopcycle-web/issues/5372
 */
interface VersionedCollectionInterface
{
    public function getVersion(): ?int;

    /**
     * Only called for a collection Doctrine does not version itself.
     *
     * @see \AppBundle\Doctrine\EventSubscriber\TaskCollectionVersionSubscriber
     */
    public function bumpVersion(): void;

    /**
     * Declared here because marking the collection dirty is how its version is
     * made to move when only its items changed.
     *
     * @see \AppBundle\Doctrine\EventSubscriber\TaskCollectionVersionSubscriber
     */
    public function setUpdatedAt(\DateTime $updatedAt);
}
