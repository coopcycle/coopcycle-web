<?php

namespace AppBundle\Message;

use AppBundle\Entity\Delivery;
use AppBundle\Entity\Task;

use Symfony\Contracts\Translation\TranslatorInterface;

class DeliveryCreated
{
    private $deliveryId;

    public function __construct(Delivery $delivery)
    {
        $this->deliveryId = $delivery->getId();
    }

    public function getDeliveryId()
    {
        return $this->deliveryId;
    }

    public function parseTitleAndBodyForPushNotification(Delivery $delivery, TranslatorInterface $translator): array
    {
        $tasks = $delivery->getTasks();
        $order = $delivery->getOrder();
        $pickup = $delivery->getPickup();
        $dropoff = $delivery->getDropoff();

        $puafdt = $pickup->getAfter()->format('H:i');
        $pubfdt = $pickup->getBefore()->format('H:i');
        $doafdt = $dropoff->getAfter()->format('H:i');
        $dobfdt = $dropoff->getBefore()->format('H:i');

        $ownerIsPickupAddr = $delivery->getOwner()->getAddress()->getStreetAddress() === $pickup->getAddress()->getStreetAddress();
        $title = $delivery->getOwner()->getName();
        $body = $translator->trans('notifications.tap_to_open');
        // Translate the ones below if needed/wanted
        $PU = "PU";
        $PUs = "PUs";
        $DO = "DO";
        $DOs = "DOs";
        $pickups_str = "pickups";
        $dropoffs_str = "dropoffs";

        if ($order && $order->isFoodtech()) {
            $title .= " -> " . $order->getShippingAddress()->getStreetAddress();
            $body = $PU. ": " . $puafdt . " | " . $DO . ": " . $doafdt;
        } else {
            $title .= " -> ";
            switch (Delivery::getType($tasks)) {
                case Delivery::TYPE_SIMPLE:
                    $body = $PU. ": " . $puafdt . "-" . $pubfdt . " | " . $DO . ": " . $doafdt . "-" . $dobfdt;
                    if (!$ownerIsPickupAddr) { // Pickup address is not the owner address
                        [$ttitle, $tbody] = $this->getTaskAddressTitleAndBody($pickup);
                        $body .= "\n" . $PU . ": " . $ttitle . ($tbody ? " (" . $tbody . ")" : '');
                    }
                    [$ttitle, $tbody] = $this->getTaskAddressTitleAndBody($dropoff);
                    $title .= $ttitle;
                    $body .= $tbody ? "\n" . $DO . ": " . $tbody : '';
                    break;
                case Delivery::TYPE_MULTI_PICKUP:
                    $pickups = array_values(array_filter($tasks, fn($t) => $t->isPickup()));
                    $title = count($pickups) . " " . $pickups_str . " -> ";
                    $firstPickup = $pickups[0];
                    $lastPickup = $pickups[ count($pickups) - 1 ];
                    $puafdt = $firstPickup->getAfter()->format('H:i');
                    $pubfdt = $lastPickup->getAfter()->format('H:i'); // Use last's "after" as "before" for multiple PUs
                    $body = $PUs. ": " . $puafdt . "-" . $pubfdt . " | " . $DO . ": " . $doafdt . "-" . $dobfdt;
                    foreach ($pickups as $pickup) {
                        [$ttitle, $tbody] = $this->getTaskAddressTitleAndBody($pickup);
                        $afdt = $pickup->getAfter()->format('H:i');
                        $bfdt = $pickup->getBefore()->format('H:i');
                        $body .= "\n" . $PU . " " . $afdt . "-" . $bfdt . ": " . $ttitle . ($tbody ? " (" . $tbody . ")" : '');
                    }
                    [$ttitle, $tbody] = $this->getTaskAddressTitleAndBody($dropoff);
                    $title .= $ttitle;
                    $body .= $tbody ? "\n" . $DO . ": " . $tbody : '';
                    break;
                case Delivery::TYPE_MULTI_DROPOFF:
                    $dropoffs = array_values(array_filter($tasks, fn($t) => $t->isDropoff()));
                    $title .= count($dropoffs) . " " . $dropoffs_str;
                    $firstDropoff = $dropoffs[0];
                    $lastDropoff = $dropoffs[ count($dropoffs) - 1 ];
                    $doafdt = $firstDropoff->getAfter()->format('H:i');
                    $dobfdt = $lastDropoff->getAfter()->format('H:i'); // Use last's "after" as "before" for multiple DOs
                    $body = $PU. ": " . $puafdt . "-" . $pubfdt . " | " . $DOs . ": " . $doafdt . "-" . $dobfdt;
                    if (!$ownerIsPickupAddr) { // Pickup address is not the owner address
                        [$ttitle, $tbody] = $this->getTaskAddressTitleAndBody($pickup);
                        $body .= "\n" . $PU . ": " . $ttitle . ($tbody ? " (" . $tbody . ")" : '');
                    }
                    foreach ($dropoffs as $dropoff) {
                        [$ttitle, $tbody] = $this->getTaskAddressTitleAndBody($dropoff);
                        $afdt = $dropoff->getAfter()->format('H:i');
                        $bfdt = $dropoff->getBefore()->format('H:i');
                        $body .= "\n" . $DO . " " . $afdt . "-" . $bfdt . ": " . $ttitle . ($tbody ? " (" . $tbody . ")" : '');
                    }
                    break;
                case Delivery::TYPE_MULTI_MULTI:
                    $pickups = array_values(array_filter($tasks, fn($t) => $t->isPickup()));
                    $dropoffs = array_values(array_filter($tasks, fn($t) => $t->isDropoff()));
                    $title = count($pickups) . " " . $pickups_str . " -> " . count($dropoffs) . " " . $dropoffs_str;
                    $firstPickup = $pickups[0];
                    $lastPickup = $pickups[ count($pickups) - 1 ];
                    $firstDropoff = $dropoffs[0];
                    $lastDropoff = $dropoffs[ count($dropoffs) - 1 ];
                    $puafdt = $firstPickup->getAfter()->format('H:i');
                    $pubfdt = $lastPickup->getAfter()->format('H:i'); // Use last's "after" as "before" for multiple PUs
                    $doafdt = $firstDropoff->getAfter()->format('H:i');
                    $dobfdt = $lastDropoff->getAfter()->format('H:i'); // Use last's "after" as "before" for multiple DOs
                    $body = $PUs. ": " . $puafdt . "-" . $pubfdt . " | " . $DOs . ": " . $doafdt . "-" . $dobfdt;
                    foreach ($pickups as $pickup) {
                        [$ttitle, $tbody] = $this->getTaskAddressTitleAndBody($pickup);
                        $afdt = $pickup->getAfter()->format('H:i');
                        $bfdt = $pickup->getBefore()->format('H:i');
                        $body .= "\n" . $PU . " " . $afdt . "-" . $bfdt . ": " . $ttitle . ($tbody ? " (" . $tbody . ")" : '');
                    }
                    foreach ($dropoffs as $dropoff) {
                        [$ttitle, $tbody] = $this->getTaskAddressTitleAndBody($dropoff);
                        $afdt = $dropoff->getAfter()->format('H:i');
                        $bfdt = $dropoff->getBefore()->format('H:i');
                        $body .= "\n" . $DO . " " . $afdt . "-" . $bfdt . ": " . $ttitle . ($tbody ? " (" . $tbody . ")" : '');
                    }
                    break;
            }
        }

        return [$title, $body];
    }

    private function getTaskAddressTitleAndBody(Task $task): array
    {
        $taskaddr = $task->getAddress();
        $title = $taskaddr->getName() ?: $taskaddr->getStreetAddress();
        $body = $taskaddr->getName() ? $taskaddr->getStreetAddress() : '';

        return [$title, $body];
    }
}
