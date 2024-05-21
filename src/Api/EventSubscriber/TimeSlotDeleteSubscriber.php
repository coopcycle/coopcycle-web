<?php

namespace AppBundle\Api\EventSubscriber;

use ApiPlatform\Core\EventListener\EventPriorities;
use AppBundle\Validator\Constraints\TimeSlotDelete;
use Psr\Log\LoggerInterface;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpKernel\Event\ViewEvent;
use Symfony\Component\HttpKernel\KernelEvents;
use Symfony\Component\Validator\Validator\ValidatorInterface;


final class TimeSlotDeleteSubscriber implements EventSubscriberInterface
{


    public function __construct(
        protected ValidatorInterface $validator,
        protected LoggerInterface $logger
    ) {}

    /**
     * @return array
     */
    public static function getSubscribedEvents()
    {
        // @see https://api-platform.com/docs/core/events/#built-in-event-listeners
        return [
            KernelEvents::VIEW => [
                ['validateForDeletion', EventPriorities::POST_VALIDATE],
            ],
        ];
    }

    public function validateForDeletion(ViewEvent $event)
    {
        $request = $event->getRequest();

        if (!('api_time_slots_delete_item' === $request->attributes->get('_route') && $request->isMethod('DELETE'))) {
            return;
        }

        $this->logger->debug('Entering pricing rule delete listener');

        $timeSlot = $event->getControllerResult();


        $violations = $this->validator->validate($timeSlot, new TimeSlotDelete());


        if (count($violations) > 0) {
            $event->setResponse(new JsonResponse(
                [$violations->get(0)->getPropertyPath() => json_decode($violations->get(0)->getMessage())],
                400
            ));
        }
    }
}
