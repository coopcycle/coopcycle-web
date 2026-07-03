<?php

namespace Tests\AppBundle\Entity;

use AppBundle\Entity\PackageSet;
use AppBundle\Entity\Store;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Validator\ValidatorBuilder;

class StoreTest extends TestCase
{
    public function testPackagesRequiredNeedsPackageSet(): void
    {
        $validator = (new ValidatorBuilder())
            ->enableAnnotationMapping()
            ->getValidator();

        $store = new Store();
        $store->setPackagesRequired(true);
        $store->setPackageSet(null);

        $violations = $validator->validate($store);
        $this->assertCount(1, $violations);
        $this->assertSame('store.packages_required.package_set_required', $violations->get(0)->getMessageTemplate());
        $this->assertSame('packagesRequired', $violations->get(0)->getPropertyPath());

        $store = new Store();
        $store->setPackagesRequired(false);

        $violations = $validator->validate($store);
        $this->assertCount(0, $violations);

        $store = new Store();
        $store->setPackagesRequired(true);
        $store->setPackageSet(new PackageSet());

        $violations = $validator->validate($store);
        $this->assertCount(0, $violations);
    }
}
