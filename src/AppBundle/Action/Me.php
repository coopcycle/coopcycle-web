<?php

namespace AppBundle\Action;

use AppBundle\Entity\ApiUser;
use AppBundle\Entity\Delivery;
use AppBundle\Entity\Task;
use AppBundle\Entity\TaskList;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Criteria;
use Doctrine\ORM\Query\Expr;
use Sensio\Bundle\FrameworkExtraBundle\Configuration\Method;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\JsonResponse;

class Me
{
    use ActionTrait;

    /**
     * @Route(
     *   name="my_tasks",
     *   path="/me/tasks/{date}",
     *   defaults={
     *     "_api_resource_class"=Task::class,
     *     "_api_collection_operation_name"="my_tasks"
     *   }
     * )
     * @Method("GET")
     */
    public function tasksAction($date)
    {
        $date = new \DateTime($date);

        $taskList = $this->getDoctrine()
            ->getRepository(TaskList::class)
            ->findOneBy([
                'courier' => $this->getUser(),
                'date' => $date
            ]);

        $tasks = [];
        if ($taskList) {
            $tasks = $taskList->getTasks();
        }

        return $tasks;
    }

    /**
     * @Route(
     *     name="me_status",
     *     path="/me/status",
     * )
     * @Method("GET")
     */
    public function statusAction()
    {
        $user = $this->getUser();

        $status = 'AVAILABLE';

        // Check if courier has accepted a delivery previously
        $delivery = $this->doctrine
            ->getRepository(Delivery::class)
            ->findOneBy([
                'courier' => $user,
                'status' => ['DISPATCHED', 'PICKED'],
            ]);

        if (null !== $delivery) {
            $status = 'DELIVERING';
        }

        $data = [
            'status' => $status,
        ];

        if (null !== $delivery) {

            $order = null;
            if (null !== $delivery->getOrder()) {
                $order = [
                    'id' => $delivery->getOrder()->getId(),
                ];
            }

            $data['delivery'] = [
                'id' => $delivery->getId(),
                'status' => $delivery->getStatus(),
                'originAddress' => [
                    'longitude' => $delivery->getOriginAddress()->getGeo()->getLongitude(),
                    'latitude' => $delivery->getOriginAddress()->getGeo()->getLatitude(),
                ],
                'deliveryAddress' => [
                    'longitude' => $delivery->getDeliveryAddress()->getGeo()->getLongitude(),
                    'latitude' => $delivery->getDeliveryAddress()->getGeo()->getLatitude(),
                ],
                'order' => $order,
            ];
        }

        return new JsonResponse($data);
    }

    /**
     * @Route(path="/me", name="me",
     *  defaults={
     *   "_api_resource_class"=ApiUser::class,
     *   "_api_collection_operation_name"="me",
     * })
     * @Method("GET")
     */
    public function meAction()
    {
        return $this->getUser();
    }
}
