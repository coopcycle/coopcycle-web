<?php

namespace AppBundle\Tests\Action;

use AppBundle\Action\Order\Accept;
use AppBundle\Entity;
use AppBundle\Service\PaymentService;
use AppBundle\Service\RoutingInterface;
use Doctrine\Bundle\DoctrineBundle\Registry as DoctrineRegistry;
use PHPUnit\Framework\TestCase as BaseTestCase;
use Predis\Client as Redis;
use Symfony\Component\EventDispatcher\EventDispatcher;
use Symfony\Component\Security\Core\Authentication\Token\Storage\TokenStorageInterface;
use Symfony\Component\Security\Core\Authentication\Token\TokenInterface;
use Symfony\Component\Serializer\SerializerInterface;

class TestCase extends BaseTestCase
{
    protected $action;
    protected $userProphecy;
    protected $user;
    protected $actionClass;
    protected $eventDispatcher;

    public function setUp()
    {
        $tokenStorage = $this->prophesize(TokenStorageInterface::class);
        $this->redisProphecy = $this->prophesize(Redis::class);
        $deliveryRepository = $this->prophesize(Entity\DeliveryRepository::class);
        $serializer = $this->prophesize(SerializerInterface::class);
        $doctrine = $this->prophesize(DoctrineRegistry::class);
        $this->eventDispatcher = $this->prophesize(EventDispatcher::class);
        $paymentService = $this->prophesize(PaymentService::class);
        $routing = $this->prophesize(RoutingInterface::class);

        $this->user = new Entity\ApiUser();

        $token = $this->prophesize(TokenInterface::class);
        $token->getUser()->willReturn($this->user);

        $tokenStorage->getToken()->willReturn($token->reveal());

        $this->action = new $this->actionClass(
            $tokenStorage->reveal(), $this->redisProphecy->reveal(),
            $deliveryRepository->reveal(), $serializer->reveal(), $doctrine->reveal(),
            $this->eventDispatcher->reveal(), $paymentService->reveal(), $routing->reveal());
    }

    protected static function setEntityId($entity, $value)
    {
        $reflection = new \ReflectionObject($entity);
        $property = $reflection->getProperty('id');
        $property->setAccessible(true);
        $property->setValue($entity, $value);
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
