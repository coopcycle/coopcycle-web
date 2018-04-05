<?php

namespace AppBundle\Controller\Utils;

use AppBundle\Entity\Delivery;
use AppBundle\Entity\Restaurant;
use AppBundle\Sylius\Order\OrderInterface;
use AppBundle\Utils\AccessControl;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;

trait AccessControlTrait
{
    protected function accessControl($object)
    {
        if ($object instanceof Delivery) {
            if (!AccessControl::delivery($this->getUser(), $object)) {
                throw new AccessDeniedHttpException();
            }
        }

        if ($object instanceof OrderInterface) {
            if (!AccessControl::order($this->getUser(), $object)) {
                throw new AccessDeniedHttpException();
            }
        }

        if ($object instanceof Restaurant) {
            if (!AccessControl::restaurant($this->getUser(), $object)) {
                throw new AccessDeniedHttpException();
            }
        }
    }
}
