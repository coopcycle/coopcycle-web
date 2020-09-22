<?php

namespace Tests\AppBundle\Action\Order;

use AppBundle\Entity\User;
use AppBundle\Service\OrderManager;
use Doctrine\ORM\EntityRepository;
use PHPUnit\Framework\TestCase as BaseTestCase;
use Prophecy\PhpUnit\ProphecyTrait;
use Symfony\Component\Security\Core\Authentication\Token\Storage\TokenStorageInterface;
use Symfony\Component\Security\Core\Authentication\Token\TokenInterface;

abstract class TestCase extends BaseTestCase
{
    use ProphecyTrait;

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
