<?php

namespace AppBundle\Action\Task;

use AppBundle\Service\TaskManager;
use Symfony\Component\HttpFoundation\Request;

abstract class Base
{
    public function __construct(
        protected TaskManager $taskManager
    )
    {
    }

    protected function getNotes(Request $request)
    {
        $data = $request->toArray();

        if (isset($data['notes'])) {
            return $data['notes'];
        }

        // FIXME Remove when the app is ok
        return $this->getReason($request);
    }

    protected function getContactName(Request $request)
    {
        $data = $request->toArray();

        if (isset($data['contactName'])) {
            return $data['contactName'];
        }

        return '';
    }

    protected function getReason(Request $request)
    {
        $data = $request->toArray();

        if (isset($data['reason'])) {
            return $data['reason'];
        }

        return null;
    }

    protected function getNote(Request $request): ?string
    {
        $data = [];
        $content = $request->getContent();
        if (!empty($content)) {
            $data = json_decode($content, true);
        }

        if (isset($data['note'])) {
            return $data['note'];
        }

        return null;
    }

    /**
     * @throws \Exception
     */
    protected function getDateTimeKey(Request $request, string $key): ?\DateTime
    {
        $data = $request->toArray();

        return isset($data[$key]) ? new \DateTime($data[$key]) : null;
    }
}
