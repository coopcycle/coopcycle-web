<?php

namespace AppBundle\Api\EventSubscriber;

use AppBundle\Entity\Store;
use Doctrine\Common\Persistence\ManagerRegistry as DoctrineRegistry;
use ApiPlatform\Core\EventListener\EventPriorities;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\HttpKernel\Event\GetResponseForControllerResultEvent;
use Symfony\Component\HttpKernel\KernelEvents;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\Security\Core\Authentication\Token\Storage\TokenStorageInterface;

final class DeliverySubscriber implements EventSubscriberInterface
{
    private $doctrine;
    private $tokenStorage;

    public function __construct(
        DoctrineRegistry $doctrine,
        TokenStorageInterface $tokenStorage)
    {
        $this->doctrine = $doctrine;
        $this->tokenStorage = $tokenStorage;
    }

    public static function getSubscribedEvents()
    {
        return [
            KernelEvents::VIEW => ['addToStore', EventPriorities::POST_WRITE],
        ];
    }

    public function addToStore(GetResponseForControllerResultEvent $event)
    {
        $request = $event->getRequest();

        if ('api_deliveries_post_collection' !== $request->attributes->get('_route')) {
            return;
        }

        if (null !== ($token = $this->tokenStorage->getToken())) {
            if (null !== ($store = $token->getAttribute('store'))) {
                $delivery = $event->getControllerResult();
                $store->addDelivery($delivery);
                $this->doctrine->getManagerForClass(Store::class)->flush();
            }
        }
    }
}
