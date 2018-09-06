<?php

namespace AppBundle\Twig;

use Predis\Client as Redis;

class MaintenanceResolver
{
    private $redis;

    public function __construct(Redis $redis)
    {
        $this->redis = $redis;
    }

    public function isEnabled()
    {
        return !empty($this->redis->get('maintenance'));
    }
}
