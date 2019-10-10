<?php

namespace AppBundle\Api\EventSubscriber;

use ApiPlatform\Core\EventListener\EventPriorities;
use Predis\Client as Redis;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\Event\RequestEvent;
use Symfony\Component\HttpKernel\KernelEvents;
use Symfony\Component\Translation\TranslatorInterface;

final class MaintenanceSubscriber implements EventSubscriberInterface
{
    private $redis;
    private $translator;

    public function __construct(
        Redis $redis,
        TranslatorInterface $translator
    ) {
        $this->redis = $redis;
        $this->translator = $translator;
    }

    public static function getSubscribedEvents()
    {
        return [
            KernelEvents::REQUEST => ['checkMaintenance', EventPriorities::PRE_READ],
        ];
    }

    public function checkMaintenance(RequestEvent $event)
    {
        if (!$this->isApiRequest($event->getRequest())) {
            return;
        }

        $maintenance = $this->redis->get('maintenance');

        if (empty($maintenance)) {
            return;
        }

        $event->setResponse(new JsonResponse(['message' => $this->getMessage()], 503));
        $event->stopPropagation();
    }

    private function isApiRequest(Request $request)
    {
        return $request->attributes->has('_api_respond')
            || $request->attributes->has('_api_resource_class')
            || $request->attributes->has('_api_item_operation_name')
            || $request->attributes->has('_api_collection_operation_name');
    }

    private function getMessage()
    {
        $message = $this->redis->get('maintenance_message');

        if (!empty($message)) {

            return $message;
        }

        return $this->translator->trans('maintenance.text');
    }
}
