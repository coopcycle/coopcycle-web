<?php

namespace Tests\AppBundle\Action\Order;

use AppBundle\Entity\ApiUser;
use AppBundle\Service\OrderManager;
use Doctrine\Common\Persistence\ManagerRegistry as DoctrineRegistry;
use Doctrine\ORM\EntityRepository;
use PHPUnit\Framework\TestCase as BaseTestCase;
use SM\Factory\FactoryInterface as StateMachineFactoryInterface;
use Symfony\Component\Security\Core\Authentication\Token\Storage\TokenStorageInterface;
use Symfony\Component\Security\Core\Authentication\Token\TokenInterface;

abstract class TestCase extends BaseTestCase
{
    protected $action;
    protected $user;
    protected $actionClass;

    protected $tokenStorage;
    protected $doctrine;

    public function setUp()
    {
        $this->tokenStorage = $this->prophesize(TokenStorageInterface::class);
        $this->doctrine = $this->prophesize(DoctrineRegistry::class);
        $this->stateMachineFactory = $this->prophesize(StateMachineFactoryInterface::class);

        $this->user = new ApiUser();

        $token = $this->prophesize(TokenInterface::class);
        $token
            ->getUser()
            ->willReturn($this->user);

        $this->tokenStorage
            ->getToken()
            ->willReturn($token->reveal());

        $this->orderManager = $this->prophesize(OrderManager::class);
        $this->action = $this->createAction($this->orderManager->reveal());
    }

    protected function createAction(OrderManager $orderManager)
    {
        return new $this->actionClass(
            $this->tokenStorage->reveal(),
            $this->doctrine->reveal(),
            $orderManager,
            $this->stateMachineFactory->reveal()
        );
    }

    protected function assertRoleThrowsException($args, $role, $exceptionClass)
    {
        try {
            $this->user->setRoles([$role]);
            call_user_func_array($this->action, $args);
        } catch (\Exception $e) {
            $this->assertInstanceOf($exceptionClass, $e);
        }
    }
}
