<?php

namespace AppBundle\EventSubscriber;

use Psr\Log\LoggerInterface;
use Symfony\Component\HttpKernel\Event\PostResponseEvent;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\HttpKernel\KernelEvents;

class ApiLogSubscriber implements EventSubscriberInterface
{
    private $logger;

    private $ignoredPaths = [
        '/api/docs',
    ];

    private $secretPaths = [
        '/api/login_check',
        '/api/register',
        '/api/token/refresh',
    ];

    public function __construct(LoggerInterface $logger)
    {
        $this->logger = $logger;
    }

    public function onKernelTerminate(PostResponseEvent $event)
    {
        $request = $event->getRequest();

        foreach ($this->ignoredPaths as $pathInfo) {
            if ($pathInfo === $request->getPathInfo()) {

                return;
            }
        }

        // Avoid logging sensitive information
        // FIXME Hide sensitive info in formatter
        foreach ($this->secretPaths as $pathInfo) {
            if ($pathInfo === $request->getPathInfo()) {

                return;
            }
        }

        if (0 !== strpos($request->getPathInfo(), '/api')) {

            return;
        }

        $response = $event->getResponse();

        $this->logger->info($request->getPathInfo(), [
            'request' => $request,
            'response' => $response
        ]);
    }

    public static function getSubscribedEvents()
    {
        return array(
            KernelEvents::TERMINATE => 'onKernelTerminate',
        );
    }
}
