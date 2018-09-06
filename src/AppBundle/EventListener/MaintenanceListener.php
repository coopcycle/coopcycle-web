<?php

namespace AppBundle\EventListener;

use Predis\Client as Redis;
use Symfony\Bridge\Twig\TwigEngine;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Event\GetResponseEvent;
use Symfony\Component\Security\Core\Authentication\Token\Storage\TokenStorageInterface;
use Symfony\Component\Security\Core\Authorization\AuthorizationCheckerInterface;

class MaintenanceListener
{
    private $authorizationChecker;
    private $tokenStorage;
    private $redis;
    private $templating;

    public function __construct(
        AuthorizationCheckerInterface $authorizationChecker,
        TokenStorageInterface $tokenStorage,
        Redis $redis,
        TwigEngine $templating)
    {
        $this->authorizationChecker = $authorizationChecker;
        $this->tokenStorage = $tokenStorage;
        $this->redis = $redis;
        $this->templating = $templating;
    }

    public function onKernelRequest(GetResponseEvent $event)
    {
        if (!$event->isMasterRequest()) {
            return;
        }

        $request = $event->getRequest();

        if (null === $token = $this->tokenStorage->getToken()) {
            return;
        }

        $maintenance = $this->redis->get('maintenance');

        if ($maintenance && !$this->authorizationChecker->isGranted('ROLE_ADMIN')) {
            $content = $this->templating->render('@App/maintenance.html.twig');
            $event->setResponse(new Response($content, 503));
            $event->stopPropagation();
        }
    }
}
