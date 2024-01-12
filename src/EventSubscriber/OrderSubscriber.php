<?php

namespace AppBundle\EventSubscriber;

use AppBundle\Entity\Sylius\Order;
use AppBundle\Utils\OrderTimeHelper;
use ApiPlatform\Core\DataPersister\DataPersisterInterface;
use ApiPlatform\Core\EventListener\EventPriorities;
use ApiPlatform\Core\Validator\ValidatorInterface;
use AppBundle\Utils\ValidationUtils;
use Carbon\Carbon;
use Psr\Log\LoggerInterface;
use Sylius\Component\Order\Processor\OrderProcessorInterface;
use Symfony\Component\EventDispatcher\Event;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\Event\ViewEvent;
use Symfony\Component\HttpKernel\KernelEvents;
use Symfony\Component\Security\Core\Authentication\Token\Storage\TokenStorageInterface;

final class OrderSubscriber implements EventSubscriberInterface
{
    private $tokenStorage;
    private $orderTimeHelper;
    private $validator;
    private $dataPersister;
    private $orderProcessor;

    public function __construct(
        TokenStorageInterface $tokenStorage,
        OrderTimeHelper $orderTimeHelper,
        ValidatorInterface $validator,
        DataPersisterInterface $dataPersister,
        OrderProcessorInterface $orderProcessor,
        private LoggerInterface $checkoutLogger,
    ) {
        $this->tokenStorage = $tokenStorage;
        $this->orderTimeHelper = $orderTimeHelper;
        $this->validator = $validator;
        $this->dataPersister = $dataPersister;
        $this->orderProcessor = $orderProcessor;
    }

    /**
     * @return array
     */
    public static function getSubscribedEvents()
    {
        return [
            KernelEvents::VIEW => [
                ['preValidate', EventPriorities::PRE_VALIDATE],
                ['timingResponse', EventPriorities::PRE_VALIDATE],
                ['validateResponse', EventPriorities::POST_VALIDATE],
                ['process', EventPriorities::PRE_WRITE],
                ['deleteItemPostWrite', EventPriorities::POST_WRITE],
                ['logPostWrite', EventPriorities::POST_WRITE],
            ],
        ];
    }

    private function getUser()
    {
        if (null === $token = $this->tokenStorage->getToken()) {
            return;
        }

        if (!is_object($user = $token->getUser())) {
            // e.g. anonymous authentication
            return;
        }

        return $user;
    }

    public function preValidate(ViewEvent $event)
    {
        $request = $event->getRequest();
        $result = $event->getControllerResult();

        if (!($result instanceof Order && Request::METHOD_POST === $request->getMethod())) {
            return;
        }

        $order = $result;

        // // Convert date to DateTime
        // if (!$delivery->getDate() instanceof \DateTime) {
        //     $delivery->setDate(new \DateTime($delivery->getDate()));
        // }

        $user = $this->getUser();

        // Make sure customer is set
        if (null === $order->getCustomer() && null !== $user) {
            $order->setCustomer($this->getUser()->getCustomer());
        }

        if ($request->attributes->get('_route') === 'api_orders_post_collection'
            && $order->hasVendor() && null === $order->getId() && null === $order->getShippingTimeRange()) {
            $shippingTimeRange = $this->orderTimeHelper->getShippingTimeRange($order);
            $order->setShippingTimeRange($shippingTimeRange);
        }

        $event->setControllerResult($order);
    }

    // FIXME Remove this listener once https://github.com/api-platform/core/pull/3150 is merged
    public function timingResponse(ViewEvent $event)
    {
        $request = $event->getRequest();

        $routes = [
            'api_orders_get_cart_timing_item',
            'api_orders_timing_collection',
        ];

        if (!in_array($request->attributes->get('_route'), $routes)) {
            return;
        }

        $order = $event->getControllerResult();

        if (!$order->hasVendor()) {
            return;
        }

        $timing = $this->orderTimeHelper->getTimeInfo($order);

        $timing['choices'] = array_map(function ($range) {
            [ $lower, $upper ] = $range;

            return Carbon::instance(Carbon::parse($lower))
                ->average(Carbon::parse($upper))
                ->format(\DateTime::ATOM);
        }, $timing['ranges']);

        $event->setControllerResult(new JsonResponse($timing));
    }

    public function validateResponse(ViewEvent $event)
    {
        $request = $event->getRequest();

        if ($request->attributes->get('_route') !== 'api_orders_validate_item') {
            return;
        }

        $controllerResult = $event->getControllerResult();

        $this->validator->validate($controllerResult);
    }

    public function deleteItemPostWrite(ViewEvent $event)
    {
        $request = $event->getRequest();

        if ($request->attributes->get('_route') !== 'api_orders_delete_item_item') {
            return;
        }

        $controllerResult = $event->getControllerResult();
        $persistResult = $this->dataPersister->persist($controllerResult);
        $event->setControllerResult($persistResult);
    }

    public function process(ViewEvent $event)
    {
        $resource = $event->getControllerResult();
        $request = $event->getRequest();
        $method = $request->getMethod();

        if (!$resource instanceof Order || Request::METHOD_PUT !== $method) {
            return;
        }

        if ($resource->getState() !== Order::STATE_CART) {
            return;
        }

        $this->checkoutLogger->info(sprintf('Order #%d | OrderSubscriber | started orderProcessor->process | request: %s | %s',
            $resource->getId(), $method, $request->getRequestUri()));

        $this->orderProcessor->process($resource);
    }

    public function logPostWrite(ViewEvent $event)
    {
        $resource = $event->getControllerResult();
        $request = $event->getRequest();
        $method = $request->getMethod();

        if (!$resource instanceof Order || !(Request::METHOD_POST === $method || Request::METHOD_PUT === $method || Request::METHOD_DELETE === $method)) {
            return;
        }

        $this->checkoutLogger->info(sprintf('Order #%d updated in the database | OrderSubscriber | request: %s | %s',
            $resource->getId(), $method, $request->getRequestUri()));

        // added to debug the issues with invalid orders in the database, including multiple delivery fees:
        // probably due to the race conditions between instances
        $errors = $this->validator->validate($resource);
        if ($errors && $errors->count() > 0) {
            $message = sprintf('Order #%d has errors: %s | OrderSubscriber',
                $resource->getId(), json_encode(ValidationUtils::serializeViolationList($errors)));

            $this->checkoutLogger->error($message);
        }
    }
}
