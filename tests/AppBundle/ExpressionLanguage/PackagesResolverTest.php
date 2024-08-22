<?php

namespace Tests\AppBundle\ExpressionLanguage;

use AppBundle\Entity\Delivery;
use AppBundle\Entity\Package;
use AppBundle\Entity\Task;
use AppBundle\ExpressionLanguage\PackagesResolver;
use PHPUnit\Framework\TestCase;

class PackagesResolverTest extends TestCase
{
    public function testContainsAtLeastOne()
    {
        $delivery = new Delivery();

        $packageOne = new Package();
        $packageOne->setName('PackageOne');
        $packageTwo = new Package();
        $packageTwo->setName('PackageTwo');

        $delivery->addPackageWithQuantity($packageOne, 2);

        $resolver = new PackagesResolver($delivery);

        $this->assertTrue($resolver->containsAtLeastOne('PackageOne'));
        $this->assertFalse($resolver->containsAtLeastOne('PackageTwo'));
    }

    public function testQuantity()
    {
        $delivery = new Delivery();

        $packageOne = new Package();
        $packageOne->setName('PackageOne');
        $packageTwo = new Package();
        $packageTwo->setName('PackageTwo');

        $delivery->addPackageWithQuantity($packageOne, 2);

        $resolver = new PackagesResolver($delivery);

        $this->assertEquals(2, $resolver->quantity('PackageOne'));
        $this->assertEquals(0, $resolver->quantity('PackageTwo'));
    }

    public function testGetTotalVolumeUnits()
    {
        $delivery = new Delivery();

        $packageOne = new Package();
        $packageOne->setName('PackageOne');
        $packageOne->setMaxVolumeUnits(1.0);

        $packageTwo = new Package();
        $packageTwo->setName('PackageTwo');
        $packageTwo->setMaxVolumeUnits(2.5);

        $delivery->addPackageWithQuantity($packageOne, 2);
        $delivery->addPackageWithQuantity($packageTwo, 2);

        $resolver = new PackagesResolver($delivery);

        $this->assertEquals(7.0, $resolver->totalVolumeUnits());
    }

    public function testQuantityWithTask()
    {
        $task = new Task();

        $packageOne = new Package();
        $packageOne->setName('PackageOne');
        $packageTwo = new Package();
        $packageTwo->setName('PackageTwo');

        $task->addPackageWithQuantity($packageOne, 2);

        $resolver = new PackagesResolver($task);

        $this->assertEquals(2, $resolver->quantity('PackageOne'));
        $this->assertEquals(0, $resolver->quantity('PackageTwo'));
    }
}
