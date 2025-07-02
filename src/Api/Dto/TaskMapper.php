<?php

namespace AppBundle\Api\Dto;

use AppBundle\Entity\Task;
use AppBundle\Utils\Barcode\BarcodeUtils;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;

class TaskMapper
{
    private const TYPE_SIMPLE = 'simple';
    private const TYPE_MULTI_DROPOFF = 'multi_dropoff';
    private const TYPE_MULTI_PICKUP = 'multi_pickup';
    private const TYPE_MULTI_MULTI = 'multi_multi';

    public function __construct(
        private readonly UrlGeneratorInterface $urlGenerator,
    )
    {
    }

    /**
     * @param Task[] $tasksInTheSameDelivery
     * @return TaskPackageDto[]
     */
    public function getPackages(Task $task, array $tasksInTheSameDelivery): array {

        $pickups = array_filter($tasksInTheSameDelivery, fn($t) => $t->isPickup());
        $dropoffs = array_filter($tasksInTheSameDelivery, fn($t) => $t->isDropoff());
        $otherTasks = array_filter($tasksInTheSameDelivery, fn($t) => $t !== $task);

        $pickupsWithPackages = array_filter($pickups, fn($t) => count($t->getPackages()) > 0);
        $dropoffsWithPackages = array_filter($dropoffs, fn($t) => count($t->getPackages()) > 0);

        $isSimple = count($pickups) === 1 && count($dropoffs) === 1;
        $isMultiDropoffs = count($pickups) === 1 && count($dropoffs) > 1;
        $isMultiPickups = count($pickups) > 1 && count($dropoffs) === 1;
        $isMultiMulti = count($pickups) > 1 && count($dropoffs) > 1;

        $isCleanMultiPickups = $isMultiPickups && count($dropoffsWithPackages) === 0;
        $isCleanMultiDropoffs = $isMultiDropoffs && count($pickupsWithPackages) === 0;

        if ($isCleanMultiDropoffs || $isSimple) {
            if ($task->isPickup()) {
                return $this->toSumOfPackages($otherTasks);
            }
            return $this->toPackages($task);
        }

        if ($isCleanMultiPickups) {
            if ($task->isDropoff()) {
                return $this->toSumOfPackages($otherTasks);
            }
            return $this->toPackages($task);
        }

        return $this->toPackages($task);
    }

    /**
     * @param Task[] $tasks
     * @return TaskPackageDto[]
     */
    private function toSumOfPackages(array $tasks): array
    {
        /**
         * @var TaskPackageDto[] $packageDtos
         */
        $packageDtos = [];

        foreach ($tasks as $t) {
            foreach ($t->getPackages() as $taskPackage) {
                $packageId = $taskPackage->getPackage()->getId();
                if (!isset($packageDtos[$packageId])) {
                    $packageDtos[$packageId] = $this->toPackageDto($taskPackage);
                } else {
                    $existingPackageDto = $packageDtos[$packageId];

                    $thisTaskPackageDto = $this->toPackageDto($taskPackage);

                    $existingPackageDto->quantity = $existingPackageDto->quantity + $thisTaskPackageDto->quantity;
                    $existingPackageDto->labels = array_merge(
                        $existingPackageDto->labels,
                        $thisTaskPackageDto->labels
                    );
                }
            }
        }

        return array_values($packageDtos);
    }

    private function toPackages(Task $task): array
    {
        return array_map(function (Task\Package $taskPackage) {
            return $this->toPackageDto($taskPackage);

        }, $task->getPackages()->toArray());
    }

    private function toPackageDto(Task\Package $taskPackage): TaskPackageDto
    {
        $task = $taskPackage->getTask();
        $package = $taskPackage->getPackage();

        $packageData = new TaskPackageDto();

        $packageData->short_code = $package->getShortCode();
        $packageData->name = $package->getName();
        //FIXME; why do we have name and type with the same value?
        $packageData->type = $package->getName();
        $packageData->volume_per_package = $package->getAverageVolumeUnits();
        $packageData->quantity = $taskPackage->getQuantity();

        if (!is_null($task->getId())) {
            $packageData->labels = $this->getLabels(
                $task->getId(),
                $package->getId(),
                $taskPackage->getQuantity()
            );
        } else {
            $packageData->labels = [];
        }

        return $packageData;
    }

    /**
     * @param Task[] $tasksInTheSameDelivery
     */
    public function getWeight(Task $task, array $tasksInTheSameDelivery): int|null
    {
        $otherTasks = array_filter($tasksInTheSameDelivery, fn($t) => $t !== $task);

        $type = $this->getTypeOfDelivery($tasksInTheSameDelivery);

        switch ($type) {
            case self::TYPE_MULTI_DROPOFF:
            case self::TYPE_SIMPLE:
                if ($task->isPickup()) {
                    return $this->sumOfWeight($otherTasks);
                }
                break;
            case self::TYPE_MULTI_PICKUP:
                if ($task->isDropoff()) {
                    return $this->sumOfWeight($otherTasks);
                }
                break;
        }

        return $task->getWeight();
    }

    /**
     * @return string[]
     */
    public function getLabels(int $taskId, int $packageId, int $quantity): array {
        $labels = [];

        $barcodes = BarcodeUtils::getBarcodesFromTaskAndPackageIds($taskId, $packageId, $quantity);
        foreach ($barcodes as $barcode) {
            $labelUrl = $this->urlGenerator->generate(
                'task_label_pdf',
                ['code' => $barcode, 'token' => BarcodeUtils::getToken($barcode)],
                UrlGeneratorInterface::ABSOLUTE_URL
            );
            $labels[] = $labelUrl;
        }

        return $labels;
    }

    public function getBarcode(Task $task): array
    {
        $barcode = BarcodeUtils::getRawBarcodeFromTask($task);
        $barcode_token = BarcodeUtils::getToken($barcode);
        return [
            'barcode' => $barcode,
            'label' => [
                'token' => $barcode_token,
                'url' => $this->urlGenerator->generate(
                    'task_label_pdf',
                    ['code' => $barcode, 'token' => $barcode_token],
                    UrlGeneratorInterface::ABSOLUTE_URL
                ),
            ],
        ];
    }

    private function getTypeOfDelivery(array $tasksInTheSameDelivery)
    {
        $pickups = array_filter($tasksInTheSameDelivery, fn($t) => $t->isPickup());
        $dropoffs = array_filter($tasksInTheSameDelivery, fn($t) => $t->isDropoff());

        $isSimple = count($pickups) === 1 && count($dropoffs) === 1;

        if ($isSimple) {
            return self::TYPE_SIMPLE;
        }

        $isMultiDropoffs = count($pickups) === 1 && count($dropoffs) > 1;
        $isMultiPickups = count($pickups) > 1 && count($dropoffs) === 1;

        $pickupsWithPackages = array_filter($pickups, fn($t) => count($t->getPackages()) > 0);
        $dropoffsWithPackages = array_filter($dropoffs, fn($t) => count($t->getPackages()) > 0);

        $isCleanMultiPickups = $isMultiPickups && count($dropoffsWithPackages) === 0;
        $isCleanMultiDropoffs = $isMultiDropoffs && count($pickupsWithPackages) === 0;

        if ($isCleanMultiPickups) {
            return self::TYPE_MULTI_PICKUP;
        }

        if ($isCleanMultiDropoffs) {
            return self::TYPE_MULTI_DROPOFF;
        }

        return self::TYPE_MULTI_MULTI;
    }

    private function sumOfWeight(array $tasks): null|int
    {
        return array_reduce($tasks, function ($carry, $item) {

            if (!is_null($item->getWeight())) {
                $carry += $item->getWeight();
            }

            return $carry;

        }, null);
    }
}
