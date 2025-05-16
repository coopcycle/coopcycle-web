<?php

namespace AppBundle\Action\PackageSet;

use AppBundle\Api\Dto\ResourceApplication;
use AppBundle\Entity\PackageSet;
use AppBundle\Service\PackageSetManager;

class Applications
{
    public function __construct(
        protected PackageSetManager $packageSetManager,
    )
    {}

    public function __invoke(PackageSet $data)
    {
        return array_map(
            fn ($object) => new ResourceApplication($object),
            $this->packageSetManager->getPackageSetApplications($data)
        );
    }
}
