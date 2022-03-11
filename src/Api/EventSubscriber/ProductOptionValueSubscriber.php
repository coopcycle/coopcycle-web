<?php

namespace AppBundle\Api\EventSubscriber;

use ApiPlatform\Core\EventListener\EventPriorities;
use AppBundle\Sylius\Product\ProductOptionValueInterface;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\EventDispatcher\GenericEvent;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\Event\ViewEvent;
use Symfony\Component\HttpKernel\KernelEvents;
use Symfony\Contracts\EventDispatcher\EventDispatcherInterface;

final class ProductOptionValueSubscriber implements EventSubscriberInterface
{
    private $dispatcher;

    public function __construct(EventDispatcherInterface $dispatcher)
    {
        $this->dispatcher = $dispatcher;
    }

    /**
     * @return array
     */
    public static function getSubscribedEvents()
    {
        return [
            KernelEvents::VIEW => ['clearTwigCache', EventPriorities::POST_WRITE],
        ];
    }

    public function clearTwigCache(ViewEvent $event)
    {
        $request = $event->getRequest();
        $data = $event->getControllerResult();

        if (Request::METHOD_PUT !== $request->getMethod()) {
            return;
        }

        if (!$data instanceof ProductOptionValueInterface) {
            return;
        }

        $restaurant = $data->getOption()->getRestaurant();

        $this->dispatcher->dispatch(new GenericEvent($restaurant), 'catalog.updated');
    }
}
