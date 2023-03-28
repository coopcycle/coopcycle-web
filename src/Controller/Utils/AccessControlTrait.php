<?php

namespace AppBundle\Controller\Utils;

use AppBundle\Entity\Delivery;
use AppBundle\Entity\LocalBusiness;
use AppBundle\Entity\Store;
use AppBundle\Entity\User;
use AppBundle\Utils\AccessControl;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;

trait AccessControlTrait
{
    /**
     * @param Delivery|LocalBusiness|Store|User $object
     */
    protected function accessControl($object, $attribute = 'edit')
    {
        if ($object instanceof Delivery) {
            $this->denyAccessUnlessGranted($attribute, $object);
        }

        if ($object instanceof LocalBusiness) {
            $this->denyAccessUnlessGranted($attribute, $object);
        }

        if ($object instanceof Store) {
            $this->denyAccessUnlessGranted($attribute, $object);
        }

        if ($object instanceof User) {
            $this->denyAccessUnlessGranted($attribute, $object);
        }
    }
}
