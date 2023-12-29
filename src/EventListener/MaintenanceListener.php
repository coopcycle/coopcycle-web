<?php

namespace AppBundle\EventListener;

use AppBundle\Service\MaintenanceManager;
use Jaybizzle\CrawlerDetect\CrawlerDetect;
use Redis;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Event\RequestEvent;
use Symfony\Contracts\Translation\TranslatorInterface;
use Twig\Environment as TwigEnvironment;

class MaintenanceListener
{
    private $redis;
    private $translator;
    private $templating;
    private $patterns = [
        '#^/login#',
        '#^/api/routing#',
        '#^/api/settings#',
        '#^/invitation/define-password#',
        '#^/resetting#',
        '#^/js/routing#'
    ];

    public function __construct(
        MaintenanceManager $maintenance,
        Redis $redis,
        TranslatorInterface $translator,
        TwigEnvironment $templating)
    {
        $this->maintenance = $maintenance;
        $this->redis = $redis;
        $this->translator = $translator;
        $this->templating = $templating;
    }

    public function onKernelRequest(RequestEvent $event)
    {
        if (!$event->isMainRequest()) {
            return;
        }

        $request = $event->getRequest();

        $crawlerDetect = new CrawlerDetect();

        // Let crawlers browse the website
        if ($crawlerDetect->isCrawler($request->headers->get('User-Agent'))) {
            return;
        }

        $maintenance = $this->redis->get('maintenance');

        if ($maintenance && !$this->maintenance->canBypass()) {

            foreach ($this->patterns as $pattern) {
                if (preg_match($pattern, rawurldecode($request->getPathInfo()))) {
                    return;
                }
            }

            $content = $this->templating->render('maintenance.html.twig', [
                'message' => $this->getMessage(),
            ]);

            $event->setResponse(new Response($content, 503));
            $event->stopPropagation();
        }
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
