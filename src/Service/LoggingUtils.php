<?php

namespace AppBundle\Service;

class LoggingUtils
{
    public function getBacktrace(int $firstFrame = 2, int $lastFrame = 4): string
    {
        $backtrace = debug_backtrace(DEBUG_BACKTRACE_IGNORE_ARGS,$lastFrame + 1);

        $stack = [];

        for($i = $firstFrame + 1; $i < count($backtrace); $i++) {
            $callerFunction = $backtrace[$i]['function'];
            $callerPoint = $backtrace[$i - 1];
            $stack[] = new StackItem($callerFunction, $callerPoint['file'], $callerPoint['line']);
        }

        return implode(' | ', $stack);
    }

    public function getCaller(): string {
        /**
         * Example:
         * (0) | function: getCaller | file: /var/www/html/src/Controller/RestaurantController.php | line: 907
         * (1) | function: persistAndFlushCart | file: /var/www/html/src/Controller/RestaurantController.php | line: 639
         * (2) | function: addProductToCartAction | file: /var/www/html/vendor/symfony/symfony/src/Symfony/Component/HttpKernel/HttpKernel.php | line: 163
         */

        $frameNumber = 2 + 1; // 2 frames from this function + 1 frame from getBacktrace

        return $this->getBacktrace($frameNumber, $frameNumber);
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

class StackItem
{
    public function __construct(
        public string $function,
        public string $file,
        public int $line
    ) {}

    public function __toString(): string
    {
        return sprintf('function: %s file: %s line: %s', $this->function, $this->file, $this->line);
    }
}
