<?php

namespace AppBundle\Twig;

use Predis\Client as Redis;

class BannerResolver
{
    private $redis;

    public function __construct(Redis $redis)
    {
        $this->redis = $redis;
    }

    public function isEnabled()
    {
        return !empty($this->redis->get('banner'));
    }

    public function getMessage()
    {
        return $this->redis->get('banner_message');
    }
}
