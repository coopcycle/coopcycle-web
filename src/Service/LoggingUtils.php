<?php

namespace AppBundle\Service;

class LoggingUtils
{
    public function getCallerAtFrame($frameNumber): string {
        $backtrace = debug_backtrace(DEBUG_BACKTRACE_IGNORE_ARGS,$frameNumber + 1);

        $callerFunction = $backtrace[$frameNumber]['function'];
        $callerPoint = $backtrace[$frameNumber - 1];
        return sprintf('function: %s | file: %s | line: %s', $callerFunction, $callerPoint['file'], $callerPoint['line']);
    }

    public function getCaller(): string {
        /**
         * Example:
         * (0) | function: getCaller | file: /var/www/html/src/Controller/RestaurantController.php | line: 907
         * (1) | function: persistAndFlushCart | file: /var/www/html/src/Controller/RestaurantController.php | line: 639
         * (2) | function: addProductToCartAction | file: /var/www/html/vendor/symfony/symfony/src/Symfony/Component/HttpKernel/HttpKernel.php | line: 163
         */

        $frameNumber = 2 + 1; // 2 frames from this function + 1 frame from getCallerAtFrame
        
        return $this->getCallerAtFrame($frameNumber);
    }

    public function getOrderId($order): string {
        $isPersisted = $order->getId() !== null;

        if ($isPersisted) {
            return sprintf('#%d', $order->getId());
        } else {
            return sprintf('(not persisted yet; created_at = %s)', $order->getCreatedAt()->format(\DateTime::ATOM));
        }
    }
}
