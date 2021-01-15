<?php

namespace AppBundle\Twig;

use Redis;
use Twig\Extension\RuntimeExtensionInterface;

class MaintenanceResolver implements RuntimeExtensionInterface
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
