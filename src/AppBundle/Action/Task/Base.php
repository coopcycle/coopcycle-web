<?php

namespace AppBundle\Action\Task;

use AppBundle\Action\Utils\TokenStorageTrait;
use AppBundle\Service\TaskManager;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Security\Core\Authentication\Token\Storage\TokenStorageInterface;

abstract class Base
{
    protected $tokenStorage;
    protected $taskManager;

    use TokenStorageTrait;

    public function __construct(TokenStorageInterface $tokenStorage, TaskManager $taskManager)
    {
        $this->tokenStorage = $tokenStorage;
        $this->taskManager = $taskManager;
    }

    protected function getNotes(Request $request)
    {
        $data = [];
        $content = $request->getContent();
        if (!empty($content)) {
            $data = json_decode($content, true);
        }

        if (isset($data['notes'])) {
            return $data['notes'];
        }

        // FIXME Remove when the app is ok
        if (isset($data['reason'])) {
            return $data['reason'];
        }
    }

    protected function getContactName(Request $request)
    {
        $data = [];
        $content = $request->getContent();
        if (!empty($content)) {
            $data = json_decode($content, true);
        }

        if (isset($data['contactName'])) {
            return $data['contactName'];
        }

        return '';
    }
}
