<?php

namespace AppBundle\ExpressionLanguage;

use ApiPlatform\Core\Api\IriConverterInterface;
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

        if (null !== $task->getTimeSlot()) {
            $values['time_slot'] = $this->iriConverter->getIriFromItem($task->getTimeSlot());
        } else {
            $values['time_slot'] = null;
        }

        return $values;
    }
}
