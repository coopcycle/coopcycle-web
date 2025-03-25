<?php

namespace AppBundle\EventSubscriber;

use AppBundle\Annotation\HideSoftDeleted;
use Doctrine\ORM\Mapping\Driver\AttributeReader;
use Doctrine\Persistence\ManagerRegistry;
use Symfony\Component\HttpKernel\Event\ControllerEvent;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\HttpKernel\KernelEvents;

class HideSoftDeletedSubscriber implements EventSubscriberInterface
{
    public function __construct(
        private ManagerRegistry $doctrine,
        private AttributeReader $attributeReader
    ) {}

    public function onKernelController(ControllerEvent $event)
    {
        $controller = $event->getController();

        /*
         * $controller passed can be either a class or a Closure.
         * This is not usual in Symfony but it may happen.
         * If it is a class, it comes in array format
         */
        if (!is_array($controller)) {
            return;
        }

        $hasClassAnnotation = in_array(
            HideSoftDeleted::class,
            $this->attributeReader->getClassAttributes(new \ReflectionClass($controller[0]))
        );

        $hasMethodAnnotation = in_array(
            HideSoftDeleted::class,
            $this->attributeReader->getMethodAttributes(new \ReflectionMethod($controller[0], $controller[1]))
        );

        if (!$hasClassAnnotation && !$hasMethodAnnotation) {
            return;
        }

        $this->doctrine->getManager()->getFilters()->enable('soft_deleteable');
    }

    /**
     * @return array
     */
    public static function getSubscribedEvents()
    {
        return array(
            KernelEvents::CONTROLLER => 'onKernelController',
        );
    }
}
