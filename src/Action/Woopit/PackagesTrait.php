<?php

namespace AppBundle\Action\Woopit;

use AppBundle\Entity\Package;
use AppBundle\Entity\PackageSet;
use AppBundle\Entity\Task;
use AppBundle\Entity\Woopit\QuoteRequest as WoopitQuoteRequest;
use PhpUnitsOfMeasure\PhysicalQuantity\Mass;

trait PackagesTrait
{
    protected function parseAndApplyPackages(WoopitQuoteRequest $data, Task $task)
    {
        $packageSet = $this->entityManager->getRepository(PackageSet::class)->findOneBy(['name' => 'Woopit']);
        if (null === $packageSet) {
            $packageSet = new PackageSet();
            $packageSet->setName('Woopit');

            $this->entityManager->persist($packageSet);
            $this->entityManager->flush();
        }

        if ($data->packages) {

            $packagesString = '';

            $qb = $this->entityManager->getRepository(Package::class)
                ->createQueryBuilder('p')
                ->andWhere('p.packageSet = :package_set')
                ->andWhere('p.name = :name')
                ->setParameter('package_set', $packageSet);

            foreach ($data->packages as $package) {

                $packageName = $this->generatePackageName($package);

                $qb->setParameter('name', $packageName);
                $p = $qb->getQuery()->getOneOrNullResult();

                if (null === $p) {

                    $p = new Package();
                    $p->setPackageSet($packageSet);

                    $p->setName($packageName);
                    $p->setDescription($packageName);
                    $p->setAverageVolumeUnits(1);
                    $p->setMaxVolumeUnits(1);
                    $p->setColor('#FFFFFF');
                    $p->setAverageWeight($this->convertWeight($package['weight']));
                    $p->setMaxWeight($this->convertWeight($package['weight']));
                    $p->setShortCode(strtoupper(substr($packageName, 0 ,2)));

                    $this->entityManager->persist($p);
                    $this->entityManager->flush();
                }

                $task->addPackageWithQuantity($p, $package['quantity']);
            }
        }
    }

    private function generatePackageName(array $data): string
    {
        $params = [];
        foreach (['width', 'height', 'length', 'weight'] as $key) {
            $params[] = $data[$key]['value'] . $data[$key]['unit'];
        }

        return sprintf('%s x %s x %s, %s', ...$params);
    }

    private function convertWeight(array $data): int|float
    {
        $mass = new Mass($data['value'], $data['unit']);

        return $mass->toUnit('g');
    }
}
