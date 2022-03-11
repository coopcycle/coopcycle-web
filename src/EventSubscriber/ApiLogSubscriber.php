<?php

namespace AppBundle\EventSubscriber;

use Monolog\Logger;
use Psr\Log\LoggerInterface;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Event\TerminateEvent;
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
        // '/api/token/refresh',
    ];

    public function __construct(LoggerInterface $logger)
    {
        $this->logger = $logger;
    }

    public function onKernelTerminate(TerminateEvent $event)
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

        $level = $this->getLogLevel($response);
        $message = sprintf('%s %s %d', $request->getMethod(), $request->getPathInfo(), $response->getStatusCode());

        $this->logger->log($level, $message, [
            'request' => $request,
            'response' => $response
        ]);
    }

    private function getLogLevel(Response $response)
    {
        if ($response->isClientError()) {
            return Logger::WARNING;
        } elseif ($response->isServerError()) {
            return Logger::ERROR;
        }

        return Logger::INFO;
    }

    /**
     * @return array
     */
    public static function getSubscribedEvents()
    {
        return array(
            KernelEvents::TERMINATE => 'onKernelTerminate',
        );
    }
}
