<?php

namespace AppBundle\EventSubscriber;

use AppBundle\Annotation\HideSoftDeleted;
use Doctrine\Common\Annotations\Reader as AnnotationReader;
use Doctrine\Persistence\ManagerRegistry;
use Symfony\Component\HttpKernel\Event\ControllerEvent;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\HttpKernel\KernelEvents;

class HideSoftDeletedSubscriber implements EventSubscriberInterface
{
    private $doctrine;
    private $annotationReader;

    public function __construct(ManagerRegistry $doctrine, AnnotationReader $annotationReader)
    {
        $this->doctrine = $doctrine;
        $this->annotationReader = $annotationReader;
    }

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

        $hasClassAnnotation = $this->annotationReader->getClassAnnotation(
            new \ReflectionClass($controller[0]),
            HideSoftDeleted::class
        );

        $hasMethodAnnotation = $this->annotationReader->getMethodAnnotation(
            new \ReflectionMethod($controller[0], $controller[1]),
            HideSoftDeleted::class
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
