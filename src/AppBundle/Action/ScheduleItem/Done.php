<?php

namespace AppBundle\Action\ScheduleItem;

use AppBundle\Action\ActionTrait;
use AppBundle\Entity\ScheduleItem;
use Sensio\Bundle\FrameworkExtraBundle\Configuration\Method;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;
use Symfony\Component\Routing\Annotation\Route;

class Done
{
    use ActionTrait;

    /**
     * @Route(
     *   name="api_schedule_items_put_done",
     *   path="/schedule_items/{id}/done",
     *   defaults={
     *     "_api_resource_class"=ScheduleItem::class,
     *     "_api_item_operation_name"="schedule_item_done"
     *   }
     * )
     * @Method("PUT")
     */
    public function __invoke($data)
    {
        $user = $this->getUser();

        $scheduleItem = $data;

        if ($scheduleItem->getCourier() !== $user) {
            throw new AccessDeniedHttpException(sprintf('User %s cannot change schedule item', $user->getUsername()));
        }

        $scheduleItem->setStatus(ScheduleItem::STATUS_DONE);

        return $scheduleItem;
    }
}
