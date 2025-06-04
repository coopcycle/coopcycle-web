<?php

namespace AppBundle\Api\Dto;

use AppBundle\Entity\Task;
use AppBundle\Utils\Barcode\BarcodeUtils;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;

class TaskMapper
{
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
        $taskPackages = [];

        if ($task->isPickup()) {
            // for a pickup in a delivery, the serialized weight is the sum of the dropoff weight and
            // the packages are the "sum" of the dropoffs packages
            foreach ($tasksInTheSameDelivery as $t) {
                if ($t->isPickup()) {
                    continue;
                }

                $taskPackages = array_merge($taskPackages, $t->getPackages()->toArray());
            }
        } else {
            $taskPackages = $task->getPackages()->toArray();
        }

        return array_map(function (Task\Package $taskPackage) use ($task) {
            $package = $taskPackage->getPackage();

            $packageData = new TaskPackageDto();

            $packageData->short_code = $package->getShortCode();
            $packageData->name = $package->getName();
            //FIXME; why do we have name and type with the same value?
            $packageData->type = $package->getName();
            $packageData->volume_per_package = $package->getAverageVolumeUnits();
            $packageData->quantity = $taskPackage->getQuantity();

            $packageData->labels = $this->getLabels(
                $task->getId(),
                $package->getId(),
                $taskPackage->getQuantity()
            );

            return $packageData;

        }, $taskPackages);
    }

    /**
     * @param Task[] $tasksInTheSameDelivery
     */
    public function getWeight(Task $task, array $tasksInTheSameDelivery): int|null {
        $weight = null;

        if ($task->isPickup()) {
            // for a pickup in a delivery, the serialized weight is the sum of the dropoff weight and
            // the packages are the "sum" of the dropoffs packages
            foreach ($tasksInTheSameDelivery as $t) {
                if ($t->isPickup()) {
                    continue;
                }

                if (!is_null($t->getWeight())) {
                    $weight += $t->getWeight();
                }
            }
        } else {
            $weight = $task->getWeight();
        }

        return $weight;
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
}
