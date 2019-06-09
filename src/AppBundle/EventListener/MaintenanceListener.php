<?php

namespace AppBundle\EventListener;

use Jaybizzle\CrawlerDetect\CrawlerDetect;
use Predis\Client as Redis;
use Symfony\Bridge\Twig\TwigEngine;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Event\GetResponseEvent;
use Symfony\Component\Security\Core\Authentication\Token\Storage\TokenStorageInterface;
use Symfony\Component\Security\Core\Authorization\AuthorizationCheckerInterface;
use Symfony\Component\Translation\TranslatorInterface;

class MaintenanceListener
{
    private $authorizationChecker;
    private $tokenStorage;
    private $crawlerDetect;
    private $redis;
    private $translator;
    private $templating;
    private $patterns = [
        '#^/login#',
    ];

    const APP_USER_AGENT = 'okhttp';

    public function __construct(
        AuthorizationCheckerInterface $authorizationChecker,
        TokenStorageInterface $tokenStorage,
        CrawlerDetect $crawlerDetect,
        Redis $redis,
        TranslatorInterface $translator,
        TwigEngine $templating)
    {
        $this->authorizationChecker = $authorizationChecker;
        $this->tokenStorage = $tokenStorage;
        $this->crawlerDetect = $crawlerDetect;
        $this->redis = $redis;
        $this->translator = $translator;
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

        // Let crawlers browse the website
        if ($this->crawlerDetect->isCrawler($request->headers->get('User-Agent')) && !$this->isAppUserAgent()) {
            return;
        }

        $maintenance = $this->redis->get('maintenance');
        $maintenanceMessage = $this->redis->get('maintenance_message');

        if ($maintenance && !$this->authorizationChecker->isGranted('ROLE_ADMIN')) {

            foreach ($this->patterns as $pattern) {
                if (preg_match($pattern, rawurldecode($request->getPathInfo()))) {
                    return;
                }
            }

            if (0 === strpos($request->getPathInfo(), '/api')) {
                $response = new JsonResponse(['message' => $this->getMessage()], 503);
            } else {
                $content = $this->templating->render('@App/maintenance.html.twig', [
                    'message' => $maintenanceMessage,
                ]);
                $response = new Response($content, 503);
            }

            $event->setResponse($response);
            $event->stopPropagation();
        }
    }

    private function isAppUserAgent()
    {
        $matches = $this->crawlerDetect->getMatches();

        return $matches && $matches === self::APP_USER_AGENT;
    }

    private function getMessage()
    {
        $message = $this->redis->get('maintenance_message');

        if (!empty($message)) {

            return $message;
        }

        return $this->translator->trans('maintenance.text');
    }
}
