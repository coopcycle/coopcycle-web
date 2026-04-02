<?php

namespace AppBundle\Action\Store;

use AppBundle\Security\TokenStoreExtractor;
use Carbon\Carbon;
use Ramsey\Uuid\Uuid;
use Symfony\Contracts\Translation\TranslatorInterface;

class Packages
{
    public function __invoke($data)
    {
        return $data->getPackages();
    }
}
