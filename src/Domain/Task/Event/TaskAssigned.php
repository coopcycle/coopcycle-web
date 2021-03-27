<?php

namespace AppBundle\Domain\Task\Event;

use AppBundle\Domain\DomainEvent;
use AppBundle\Domain\HasIconInterface;
use AppBundle\Domain\Task\Event;
use AppBundle\Entity\Task;
use Symfony\Component\Security\Core\User\UserInterface;

class TaskAssigned extends Event implements DomainEvent, HasIconInterface
{
    private $user;

    public function __construct(Task $task, UserInterface $user)
    {
        parent::__construct($task);

        $this->user = $user;
    }

    public function getUser()
    {
        return $this->user;
    }

    public function toPayload()
    {
        return [
            'username' => $this->getUser()->getUsername(),
        ];
    }

    public static function messageName(): string
    {
        return 'task:assigned';
    }

    public static function iconName()
    {
        return 'plus';
    }
}

