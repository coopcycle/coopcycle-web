<?php

namespace Tests\AppBundle\Action\Order;

use AppBundle\Entity\ApiUser;
use AppBundle\Service\OrderManager;
use Doctrine\ORM\EntityRepository;
use PHPUnit\Framework\TestCase as BaseTestCase;
use Symfony\Component\Security\Core\Authentication\Token\Storage\TokenStorageInterface;
use Symfony\Component\Security\Core\Authentication\Token\TokenInterface;

abstract class TestCase extends BaseTestCase
{
    protected $action;
    protected $user;
    protected $actionClass;

    protected $tokenStorage;
    protected $doctrine;

    public function setUp(): void
    {
        $this->orderManager = $this->prophesize(OrderManager::class);
        $this->action = $this->createAction($this->orderManager->reveal());
    }

    protected function createAction(OrderManager $orderManager)
    {
        return new $this->actionClass($orderManager);
    }
}
