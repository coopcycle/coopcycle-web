<?php

namespace AppBundle\LoopEat;

class Context
{
    public $logoUrl;
    public $name;
    public $customerAppUrl;
    public $hasCredentials = false;
    public $formats = [];

    public $creditsCountCents = 0;
    public $containersCount = 0;
    public $requiredAmount = 0;
    public $containers = [];
}
