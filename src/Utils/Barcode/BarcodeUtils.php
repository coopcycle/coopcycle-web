<?php

namespace AppBundle\Utils\Barcode;

use AppBundle\Entity\Task;
use AppBundle\Entity\Task\Package;

class BarcodeUtils {

    const WITHOUT_PACKAGE = '6767%03d%d%d8076';
    const WITH_PACKAGE =    '6767%03d%d%dP%dU%d8076';

    private static string $appName = "";
    private static string $salt = "";

    public static function init(string $appName, string $salt): void {
        self::$appName = $appName;
        self::$salt = $salt;
    }

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

    public static function getRawBarcodeFromTask(Task $task): string
    {
        return sprintf(
            self::WITHOUT_PACKAGE,
            1, //TODO: Dynamicly get instance
            Barcode::TYPE_TASK, $task->getId()
        );
    }

    public static function getBarcodeFromTask(Task $task): Barcode {
        $code = self::getRawBarcodeFromTask($task);
        return self::parse($code);
    }

    /**
     * Returns a list of barcodes for a package.
     * May return multiple barcodes for the same package when quantity is > 1.
     * @return Barcode[]
     */
    public static function getBarcodesFromPackage(Package $package, int $start = 0): array
    {
        $quantity = $package->getQuantity();
        $taskId = $package->getTask()->getId();
        $packageId = $package->getId();

        return self::getBarcodesFromTaskAndPackageIds($taskId, $packageId, $quantity, $start);
    }


    /**
     * @param string|Barcode $barcode
     */
    public static function getToken($barcode): string
    {
        if ($barcode instanceof Barcode) {
            $barcode = $barcode->getRawBarcode();
        }

        return hash('xxh3', sprintf("%s%s%s", self::$appName, self::$salt, $barcode));
    }

    /**
     * Returns a list of barcodes for a package.
     * May return multiple barcodes for the same package when quantity is > 1.
     * @return Barcode[]
     */
    public static function getBarcodesFromTaskAndPackageIds(int $taskId, int $packageId, int $quantity = 1, int $start = 0): array
    {
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
