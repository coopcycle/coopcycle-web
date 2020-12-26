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
     * @var Delivery|LocalBusiness|Store $object
     */
    protected function accessControl($object)
    {
        if ($object instanceof Delivery) {
            if (!AccessControl::delivery($this->getUser(), $object)) {
                throw new AccessDeniedHttpException();
            }
        }

        if ($object instanceof LocalBusiness) {
            $this->denyAccessUnlessGranted('edit', $object);
        }

        if ($object instanceof Store) {
            if (!AccessControl::store($this->getUser(), $object)) {
                throw new AccessDeniedHttpException();
            }
        }
    }
}
