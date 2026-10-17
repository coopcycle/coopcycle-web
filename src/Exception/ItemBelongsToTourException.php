<?php

namespace AppBundle\Exception;

/**
 * A task that is on a task list through a tour cannot be taken off the list on
 * its own: the tour is the item, and it is changed as a tour.
 *
 * @see \AppBundle\Service\TaskListManager::removeItem()
 */
class ItemBelongsToTourException extends \Exception
{
}
