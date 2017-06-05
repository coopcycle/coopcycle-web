<?php

namespace AppBundle\Action\Delivery;

use AppBundle\Action\ActionTrait;
use AppBundle\Entity\Delivery;
use Doctrine\Common\Persistence\ManagerRegistry;
use Sensio\Bundle\FrameworkExtraBundle\Configuration\Method;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\Security\Core\Exception\AccessDeniedException;

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
        $user = $this->getUser();

        // Only couriers can accept orders
        if (!$user->hasRole('ROLE_COURIER')) {
            throw new AccessDeniedHttpException(sprintf('User #%d cannot accept order', $user->getId()));
        }

        $delivery = $data;

        // Make sure the courier picking order is authorized
        if ($delivery->getCourier() !== $this->getUser()) {
            throw new AccessDeniedException();
        }

        $delivery->setStatus(Delivery::STATUS_PICKED);

        return $order;
    }
}
