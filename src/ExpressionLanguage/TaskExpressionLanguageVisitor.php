<?php

namespace AppBundle\ExpressionLanguage;

use ApiPlatform\Api\IriConverterInterface;
use AppBundle\Entity\Delivery;
use AppBundle\Entity\Task;

class TaskExpressionLanguageVisitor
{

    public function __construct(
        private readonly IriConverterInterface $iriConverter,
    )
    {
    }

    public static function toExpressionLanguageObject(Task $task): \stdClass
    {
        $taskObject = new \stdClass();

        $taskObject->type = $task->getType();
        $taskObject->address = $task->getAddress();
        $taskObject->createdAt = $task->getCreatedAt();
        $taskObject->after = $task->getAfter();
        $taskObject->before = $task->getBefore();
        $taskObject->doorstep = $task->isDoorstep();

        return $taskObject;
    }

    public function toExpressionLanguageValues(Task $task): array
    {
        $emptyObject = new \stdClass();
        $emptyObject->address = null;
        $emptyObject->createdAt = null;
        $emptyObject->after = null;
        $emptyObject->before = null;
        $emptyObject->doorstep = false;

        $thisObj = self::toExpressionLanguageObject($task);

        // Only in the per-point scope: this is where a rule is evaluated once per point,
        // so it can charge from the Nth pickup or dropoff on, e.g. "task.dropoffRank > 1"
        $thisObj->pickupRank = self::getRank($task, Task::TYPE_PICKUP);
        $thisObj->dropoffRank = self::getRank($task, Task::TYPE_DROPOFF);

        //FIXME: legacy properties from the days when Task and Delivery shared the same expression language structure
        // to be removed? For now, they might still be needed to maintain backwards compatibility
        // for more information see app/DoctrineMigrations/Version20250304220001.php
        $values = [
            'distance' => -1,
            //FIXME; 'vehicle' is deprecated
            'vehicle' => Delivery::VEHICLE_BIKE,
            'pickup' => $task->isPickup() ? $thisObj : $emptyObject,
            'dropoff' => $task->isDropoff() ? $thisObj : $emptyObject,
            'order' => new \stdClass(),
            'task' => $thisObj,
        ];

        $values['weight'] = $task->getWeight();
        $values['packages'] = new PackagesResolver($task);

        // Packages are usually attached to the dropoff(s), so a rule applied to a pickup
        // sees none of them via 'packages'. 'delivery' exposes the whole delivery,
        // whichever point is being evaluated.
        $values['delivery'] = DeliveryExpressionLanguageVisitor::toDeliveryObject($task->getDelivery() ?? $task);

        if (null !== $task->getTimeSlot()) {
            $values['time_slot'] = $this->iriConverter->getIriFromResource($task->getTimeSlot());
        } else {
            $values['time_slot'] = null;
        }

        return $values;
    }

    /**
     * The 1-based position of a point among the delivery's points of the same type, in the order of the delivery.
     * Cancelled points are not counted, as they are not priced.
     * Returns null when the point is not of the given type.
     */
    private static function getRank(Task $task, string $type): ?int
    {
        if ($task->getType() !== $type) {
            return null;
        }

        $delivery = $task->getDelivery();
        if (is_null($delivery)) {
            return 1;
        }

        $tasksOfType = array_values(array_filter(
            $delivery->getTasks(),
            fn(Task $t) => $t->getType() === $type && !$t->isCancelled()
        ));

        $index = array_search($task, $tasksOfType, true);
        if (false === $index) {
            return null;
        }

        return $index + 1;
    }
}
