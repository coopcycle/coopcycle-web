<?php

namespace AppBundle\Action\Delivery;

use AppBundle\Entity\Delivery;
use AppBundle\Message\DeliveryCancelled;
use AppBundle\Service\TaskManager;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;
use Symfony\Component\Messenger\MessageBusInterface;
use Symfony\Component\Security\Core\Authorization\AuthorizationCheckerInterface;

class Cancel
{
    public function __construct(
        private readonly TaskManager $taskManager,
        private readonly EntityManagerInterface $entityManager,
        private readonly MessageBusInterface $messageBus,
        private readonly AuthorizationCheckerInterface $authorizationChecker)
    {
    }

    public function __invoke(Delivery $data, Request $request)
    {
        // It would have been cleaner to implement it via a validator,
        // but DELETE operations do not trigger validation
        // @see ApiPlatform\Symfony\EventListener\ValidateListener
        foreach ($data->getTasks() as $task) {
            if ($task->isAssigned()) {
                throw new BadRequestHttpException('Tasks have already been assigned');
            }
        }

        foreach ($data->getTasks('not task.isCancelled()') as $task) {
            $this->taskManager->cancel($task);
        }

        // As we have configured write = false at operation level,
        // we have to flush changes here
        $this->entityManager->flush();

        // Let dispatchers know when a delivery has been cancelled by a store owner
        if (!$this->authorizationChecker->isGranted('ROLE_DISPATCHER')) {
            $this->messageBus->dispatch(new DeliveryCancelled($data));
        }

        return $data;
    }
}
