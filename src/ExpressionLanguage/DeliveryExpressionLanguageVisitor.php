<?php

namespace AppBundle\ExpressionLanguage;

use AppBundle\Entity\Delivery;
use AppBundle\Entity\Sylius\Order;
use AppBundle\Entity\Task;
use stdClass;

class DeliveryExpressionLanguageVisitor
{
    private function createTaskObject(?Task $task): stdClass
    {
        $taskObject = new stdClass();
        if ($task) {

            return TaskExpressionLanguageVisitor::toExpressionLanguageObject($task);
        }

        return $taskObject;
    }

    private function createOrderObject(?Order $order): stdClass
    {
        $object = new stdClass();
        if ($order) {
            $object->itemsTotal = $order->getItemsTotal();
        } else {
            $object->itemsTotal = 0;
        }

        return $object;
    }

    public function toExpressionLanguageValues(Delivery $delivery): array
    {
        $pickup = $this->createTaskObject($delivery->getPickup());
        $dropoff = $this->createTaskObject($delivery->getDropoff());
        $order = $this->createOrderObject($delivery->getOrder());

        $emptyTaskObject = new stdClass();
        $emptyTaskObject->type = '';

        return [
            'distance' => $delivery->getDistance(),
            'weight' => $delivery->getWeight(),
            'vehicle' => $delivery->getVehicle(),
            'pickup' => $pickup,
            'dropoff' => $dropoff,
            'packages' => new PackagesResolver($delivery),
            'order' => $order,
            'task' => $emptyTaskObject,
        ];
    }
}
