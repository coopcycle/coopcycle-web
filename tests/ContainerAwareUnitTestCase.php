<?php

namespace Tests;

use PHPUnit\Framework\TestCase;
require_once __DIR__.'/../app/AppKernel.php';


class ContainerAwareUnitTestCase extends TestCase {

    protected static $kernel;
    protected static $container;

    public static function setUpBeforeClass()
     {
         self::$kernel = new \AppKernel('dev', true);
         self::$kernel->boot();

         self::$container = self::$kernel->getContainer();
     }

     public function get($serviceId)
     {
         return self::$kernel->getContainer()->get($serviceId);
     }
}

?>

