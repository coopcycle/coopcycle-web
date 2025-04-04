<?php

namespace AppBundle\ExpressionLanguage;

use ApiPlatform\Core\Api\IriConverterInterface;
use AppBundle\Entity\Task;

class TaskExpressionLanguageVisitor
{

    public function __construct(
        private readonly DeliveryExpressionLanguageVisitor $deliveryExpressionLanguageVisitor,
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
        //FIXME: to be removed?; for now it might still be needed to maintain backwards compatibility
        // for move information see app/DoctrineMigrations/Version20250304220001.php
        $values = $this->deliveryExpressionLanguageVisitor->toExpressionLanguageValues($task->getDelivery());

        $emptyObject = new \stdClass();
        $emptyObject->address = null;
        $emptyObject->createdAt = null;
        $emptyObject->after = null;
        $emptyObject->before = null;
        $emptyObject->doorstep = false;

        $thisObj = self::toExpressionLanguageObject($task);

        $values['distance'] = -1;
        $values['weight'] = $task->getWeight();
        $values['pickup'] = $task->isPickup() ? $thisObj : $emptyObject;
        $values['dropoff'] = $task->isDropoff() ? $thisObj : $emptyObject;
        $values['packages'] = new PackagesResolver($task);

        if (null !== $task->getTimeSlot()) {
            $values['time_slot'] = $this->iriConverter->getIriFromItem($task->getTimeSlot());
        }

        $values['task'] = $thisObj;

        return $values;
    }
}
