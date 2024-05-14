<?php

namespace AppBundle\Action\PackageSet;

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
        return $this->packageSetManager->getPackageSetApplications($data);
    }
}
