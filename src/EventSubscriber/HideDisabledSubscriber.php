<?php

namespace AppBundle\EventSubscriber;

use AppBundle\Annotation\HideDisabled;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\HttpKernel\Event\ControllerEvent;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\HttpKernel\KernelEvents;

class HideDisabledSubscriber implements EventSubscriberInterface
{
    public function __construct(
        private EntityManagerInterface $entityManager
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

        $attribute = $this->getAttribute($controller);

        if (null !== $attribute) {
            $arguments = $attribute->getArguments();
            if (isset($arguments['classes']) && !empty($arguments['classes'])) {
                $filters = $this->entityManager->getFilters();
                if ($filters->isEnabled('disabled_filter')) {
                    $filter = $filters->getFilter('disabled_filter');
                    foreach ($arguments['classes'] as $class) {
                        $filter->add($class);
                    }
                }
            }
        }
    }

    private function getAttribute(array $controller): ?\ReflectionAttribute
    {
        $class = new \ReflectionClass($controller[0]);

        $classAttributes = $class->getAttributes(HideDisabled::class, \ReflectionAttribute::IS_INSTANCEOF);

        if (!empty($classAttributes)) {
            return current($classAttributes);
        }

        $method = new \ReflectionMethod($controller[0], $controller[1]);

        $methodAttributes = $method->getAttributes(HideDisabled::class, \ReflectionAttribute::IS_INSTANCEOF);

        if (!empty($methodAttributes)) {
            return current($methodAttributes);
        }

        return null;
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
