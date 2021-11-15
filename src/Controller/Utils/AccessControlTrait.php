<?php

namespace AppBundle\Controller\Utils;

use AppBundle\Entity\Delivery;
use AppBundle\Entity\LocalBusiness;
use AppBundle\Entity\Store;
use AppBundle\Utils\AccessControl;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;

trait AccessControlTrait
{
    /**
     * @param Delivery|LocalBusiness|Store $object
     */
    protected function accessControl($object)
    {
        if ($object instanceof Delivery) {
            $this->denyAccessUnlessGranted('edit', $object);
        }

        if ($object instanceof LocalBusiness) {
            $this->denyAccessUnlessGranted('edit', $object);
        }

        if ($object instanceof Store) {
            $this->denyAccessUnlessGranted('edit', $object);
        }
    }
}
