<?php

namespace AppBundle\EventSubscriber;

use AppBundle\Utils\Barcode\BarcodeUtils;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\HttpKernel\KernelEvents;

class BarcodeUtilsSubscriber implements EventSubscriberInterface
{
    private static bool $initialized = false;
    private string $appName;
    private string $salt;

    public function __construct(string $appName, string $salt)
    {
        $this->appName = $appName;
        $this->salt = hash('xxh3', $salt);
    }

    public function onKernelRequest(): void
    {
        if (!self::$initialized) {
            BarcodeUtils::init($this->appName, $this->salt);
            self::$initialized = true;
        }
    }

    public static function getSubscribedEvents()
    {
        return [
            KernelEvents::REQUEST => ['onKernelRequest', 255],
        ];
    }
}
