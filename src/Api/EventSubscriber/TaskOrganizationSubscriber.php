<?php

namespace AppBundle\Api\EventSubscriber;

use AppBundle\Entity\Task\Group as TaskGroup;
use AppBundle\Security\TokenStoreExtractor;
use ApiPlatform\Core\EventListener\EventPriorities;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\Event\ViewEvent;
use Symfony\Component\HttpKernel\KernelEvents;

/**
 * The listener attaches an Organization to a Task if possible
 *
 * This is useful when creating Tasks or TaskGroups, so that UniqueEntityValidator works as expected.
 * There is also AppBundle\Doctrine\EventSubscriber\OrganizationSubscriber which takes care of this,
 * but it is called before persisting the entity in database, i.e too late.
 *
 * @see AppBundle\Doctrine\EventSubscriber\OrganizationSubscriber
 */
final class TaskOrganizationSubscriber implements EventSubscriberInterface
{
    private $storeExtractor;

    public function __construct(TokenStoreExtractor $storeExtractor)
    {
        $this->storeExtractor = $storeExtractor;
    }

    public static function getSubscribedEvents()
    {
        // @see https://api-platform.com/docs/core/events/#built-in-event-listeners
        return [
            KernelEvents::VIEW => [
                ['setOrganization', EventPriorities::PRE_VALIDATE],
            ],
        ];
    }

    public function setOrganization(ViewEvent $event)
    {
        $object = $event->getControllerResult();
        $method = $event->getRequest()->getMethod();

        if (!$object instanceof TaskGroup || Request::METHOD_POST !== $method) {
            return;
        }

        // TODO Also manage Task?

        $store = $this->storeExtractor->extractStore();

        foreach ($object->getTasks() as $task) {
            $task->setOrganization($store->getOrganization());
        }
    }
}
