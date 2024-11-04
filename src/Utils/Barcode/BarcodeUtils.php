<?php

namespace AppBundle\Utils\Barcode;

use AppBundle\Entity\Task;
use AppBundle\Entity\Task\Package;

class BarcodeUtils {

    const WITHOUT_PACKAGE = '6767%03d%d%d8076';
    const WITH_PACKAGE =    '6767%03d%d%dP%dU%d8076';

    public static function parse(string $barcode): Barcode {
        $matches = [];
        if (!preg_match(
            '/6767(?<instance>[0-9]{3})(?<entity>[1-2])(?<id>[0-9]+)(P(?<package>[0-9]+))?(U(?<unit>[0-9]+))?8076/',
            $barcode,
            $matches,
            PREG_OFFSET_CAPTURE
        )) { return new Barcode($barcode); }

        return new Barcode(
            $barcode,
            $matches['entity'][0],
            $matches['id'][0],
            $matches['package'][0] ?? null,
            $matches['unit'][0] ?? null
        );
    }

    /**
     * @return Barcode[]|Barcode|null
     * @param object $entity
     */
    public static function getBarcodeFromEntity(object $entity): array|Barcode|null {
        switch (get_class($entity)) {
            case Task::class:
                return self::getBarcodeFromTask($entity);
            case Package::class:
                return self::getBarcodesFromPackage($entity);
            default:
                return null;
        }
    }

    public static function getBarcodeFromTask(Task $task): Barcode {
        $code = sprintf(
            self::WITHOUT_PACKAGE,
            1, //TODO: Dynamicly get instance
            Barcode::TYPE_TASK, $task->getId()
        );

        return self::parse($code);
    }

    /**
     * @return Barcode[]
     */
    public static function getBarcodesFromPackage(Package $package, int $start = 0): array
    {
        $quantity = $package->getQuantity();
        $taskId = $package->getTask()->getId();
        $packageId = $package->getId();

        return array_map(
            fn(int $index) => self::parse(sprintf(
                self::WITH_PACKAGE,
                1, // TODO: Dynamic instance
                Barcode::TYPE_TASK,
                $taskId,
                $packageId,
                $index + $start + 1
            )),
            range(0, $quantity - 1)
        );
    }

}
