<?php

namespace AppBundle\Exception;

/**
 * A reorder that would add or drop items is refused: ordering and membership
 * are separate operations, precisely so that an ordering write cannot be the
 * thing that unassigns somebody's work.
 *
 * @see \AppBundle\Service\TaskListManager::reorder()
 */
class OrderChangesMembershipException extends \Exception
{
}
