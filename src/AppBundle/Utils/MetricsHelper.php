<?php

namespace AppBundle\Utils;

use M6Web\Component\Statsd\Client as StatsdClient;

class MetricsHelper
{
    private $namespace;
    private $statsd;

    public function __construct($namespace, StatsdClient $statsd)
    {
        $this->namespace = $namespace;
        $this->statsd = $statsd;
    }

    private function withPrefix($key)
    {
        return sprintf('%s.%s', $this->namespace, $key);
    }

    public function incrementOrdersWaiting()
    {
        $data = [
            sprintf('%s:%s|%s', $this->withPrefix('orders.waiting'), '+1', 'g')
        ];

        $this->statsd->writeDatas($this->statsd->getServerKey('default'), $data);
    }

    public function decrementOrdersWaiting()
    {
        $data = [
            sprintf('%s:%s|%s', $this->withPrefix('orders.waiting'), '-1', 'g')
        ];

        $this->statsd->writeDatas($this->statsd->getServerKey('default'), $data);
    }
}
