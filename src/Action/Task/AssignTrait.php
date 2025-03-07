<?php

namespace AppBundle\Action\Task;

use ApiPlatform\Core\Exception\ItemNotFoundException;
use AppBundle\Entity\Task;
use AppBundle\Utils\Barcode\BarcodeUtils;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;

trait AssignTrait
{
    protected function assign(Task $task, $payload, ?Request $request = null)
    {
        $user = $this->getUser();
        if (isset($payload['username'])) {

            $user = $this->userManager->findUserByUsername($payload['username']);

            if (!$user) {

                throw new ItemNotFoundException(sprintf('User "%s" does not exist',
                    $payload['username']));
            }
        }


        if (
            $task->isAssigned() &&
            !($this->authorization->isGranted('ROLE_DISPATCHER') || $this->isTokenActionValid($task, $request))
        ) {

            throw new BadRequestHttpException(sprintf('Task #%d is already assigned to "%s"',
                $task->getId(), $task->getAssignedCourier()->getUsername()));
        }

        if ($task->isAssignedTo($user)) {

            return $task; // Do nothing
        }

        if (!is_null($task->getDelivery())) {
            $task->getDelivery()->assignTo($user);
        } else {
            $task->assignTo($user);
        }

        return $task;
    }

    private function isTokenActionValid(Task $task, ?Request $request): bool
    {
        if (is_null($request)) {
            return false;
        }
        return $request->headers->get('X-Token-Action') ===
            hash('xxh3', BarcodeUtils::getToken(sprintf("/api/tasks/%d", $task->getId())));
    }
}
