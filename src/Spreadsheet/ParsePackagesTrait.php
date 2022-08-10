<?php

namespace AppBundle\Spreadsheet;

use AppBundle\Entity\Task;
use AppBundle\Entity\Package;

trait ParsePackagesTrait
{
    private function parseAndApplyPackages(Task $task, $packagesRecord)
    {
        array_map(function ($packageString) use($task) {
            [$packageSlug, $packageQty] = explode("=", $packageString);

            $package = $this->entityManager->getRepository(Package::class)
                ->findOneBy([
                    'slug' => strtolower($packageSlug),
                ]);

            if ($package) {
                $task->addPackageWithQuantity($package, $packageQty);
            }
        }, explode(" ", $packagesRecord));
    }
}
