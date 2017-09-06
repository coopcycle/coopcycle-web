<?php

namespace AppBundle\Action\Delivery;

use AppBundle\Action\ActionTrait;
use AppBundle\Entity\Delivery;
use Doctrine\Common\Persistence\ManagerRegistry;
use Sensio\Bundle\FrameworkExtraBundle\Configuration\Method;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;
use Symfony\Component\Routing\Annotation\Route;

class Pick
{
    use ActionTrait;

    /**
     * @Route(
     *     name="delivery_pick",
     *     path="/deliveries/{id}/pick",
     *     defaults={"_api_resource_class"=Delivery::class, "_api_item_operation_name"="pick"}
     * )
     * @Method("PUT")
     */
    public function __invoke($data)
    {
        $this->verifyRole('ROLE_COURIER', 'User #%d cannot pick delivery');

        $delivery = $data;

        // Make sure the courier picking order is authorized
        if ($delivery->getCourier() !== $this->getUser()) {
            throw new AccessDeniedHttpException();
        }

        $delivery->setStatus(Delivery::STATUS_PICKED);

        return $delivery;
    }
}
