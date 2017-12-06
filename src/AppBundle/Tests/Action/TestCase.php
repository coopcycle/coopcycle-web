<?php

namespace AppBundle\Tests\Action;

use AppBundle\Action\Order\Accept;
use AppBundle\Entity;
use AppBundle\Service\DeliveryService\Factory as DeliveryServiceFactory;
use AppBundle\Service\DeliveryManager;
use AppBundle\Service\DeliveryServiceInterface;
use AppBundle\Service\OrderManager;
use AppBundle\Service\PaymentService;
use AppBundle\Service\RoutingInterface;
use Doctrine\Bundle\DoctrineBundle\Registry as DoctrineRegistry;
use PHPUnit\Framework\TestCase as BaseTestCase;
use Predis\Client as Redis;
use Sylius\Component\Taxation\Calculator\CalculatorInterface;
use Sylius\Component\Taxation\Repository\TaxCategoryRepositoryInterface;
use Sylius\Component\Taxation\Resolver\TaxRateResolverInterface;
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
        $deliveryService = $this->prophesize(DeliveryServiceInterface::class);
        $taxRateResolver = $this->prophesize(TaxRateResolverInterface::class);
        $calculator = $this->prophesize(CalculatorInterface::class);
        $taxCategoryRepository = $this->prophesize(TaxCategoryRepositoryInterface::class);
        $deliveryManager = $this->prophesize(DeliveryManager::class);

        $deliveryServiceFactory = new DeliveryServiceFactory([], $deliveryService->reveal());

        $this->user = new Entity\ApiUser();

        $token = $this->prophesize(TokenInterface::class);
        $token->getUser()->willReturn($this->user);

        $tokenStorage->getToken()->willReturn($token->reveal());

        $orderManager = new OrderManager(
            $paymentService->reveal(),
            $deliveryServiceFactory,
            $this->redisProphecy->reveal(),
            $serializer->reveal(),
            $taxRateResolver->reveal(),
            $calculator->reveal(),
            $taxCategoryRepository->reveal(),
            $deliveryManager->reveal()
        );

        $this->action = new $this->actionClass(
            $tokenStorage->reveal(), $this->redisProphecy->reveal(),
            $deliveryRepository->reveal(), $doctrine->reveal(),
            $orderManager, $routing->reveal());
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
