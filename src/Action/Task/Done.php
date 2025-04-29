<?php

namespace AppBundle\Action\Task;

use AppBundle\Service\TaskManager;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Security\Core\Authentication\Token\Storage\TokenStorageInterface;
use Symfony\Contracts\Translation\TranslatorInterface;

class Done extends Base
{
    use DoneTrait;

    public function __construct(
        TokenStorageInterface $tokenStorage,
        TaskManager $taskManager
    )
    {
        parent::__construct($tokenStorage, $taskManager);
    }

    public function __invoke($data, Request $request)
    {
        $task = $data;

        return $this->done($task, $request);
    }
}
