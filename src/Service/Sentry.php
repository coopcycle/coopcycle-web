<?php

namespace AppBundle\Service;

use Psr\Log\LoggerInterface;

class Sentry
{
    private static $typeCurrencyDeprecated = 'Deprecated: Constant NumberFormatter::TYPE_CURRENCY is deprecated';

    public function getBeforeSend(): callable
    {
        return function (\Sentry\Event $event, ?\Sentry\EventHint $hint): ?\Sentry\Event {

            if ($hint !== null && $hint->exception !== null) {
                if ($hint->exception->getMessage() === self::$typeCurrencyDeprecated) {
                    return null;
                }
            }

            return $event;
        };
    }
}
