<?php

namespace AppBundle\Tests\Action;

use AppBundle\Action\Order\Accept;
use AppBundle\Entity;
use AppBundle\Service\DeliveryManager;
use AppBundle\Service\NotificationManager;
use AppBundle\Service\OrderManager;
use AppBundle\Service\PaymentService;
use Doctrine\Common\Persistence\ManagerRegistry as DoctrineRegistry;
use Doctrine\ORM\EntityRepository;
use PHPUnit\Framework\TestCase as BaseTestCase;
use Predis\Client as Redis;
use Sylius\Component\Taxation\Calculator\CalculatorInterface;
use Sylius\Component\Taxation\Repository\TaxCategoryRepositoryInterface;
use Sylius\Component\Taxation\Resolver\TaxRateResolverInterface;
use Symfony\Component\EventDispatcher\EventDispatcher;
use Symfony\Component\ExpressionLanguage\ExpressionLanguage;
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
        $serializer = $this->prophesize(SerializerInterface::class);
        $doctrine = $this->prophesize(DoctrineRegistry::class);
        $this->eventDispatcher = $this->prophesize(EventDispatcher::class);
        $paymentService = $this->prophesize(PaymentService::class);
        $taxRateResolver = $this->prophesize(TaxRateResolverInterface::class);
        $calculator = $this->prophesize(CalculatorInterface::class);
        $taxCategoryRepository = $this->prophesize(TaxCategoryRepositoryInterface::class);
        $expressionLanguage = $this->prophesize(ExpressionLanguage::class);
        $notificationManager = $this->prophesize(NotificationManager::class);

        $this->user = new Entity\ApiUser();

        $token = $this->prophesize(TokenInterface::class);
        $token->getUser()->willReturn($this->user);

        $tokenStorage->getToken()->willReturn($token->reveal());

        $deliveryManager = new DeliveryManager(
            $doctrine->reveal(),
            $taxRateResolver->reveal(),
            $calculator->reveal(),
            $taxCategoryRepository->reveal(),
            'tva_livraison',
            $expressionLanguage->reveal(),
            $notificationManager->reveal()
        );

        $orderManager = new OrderManager(
            $paymentService->reveal(),
            $this->redisProphecy->reveal(),
            $serializer->reveal(),
            $taxRateResolver->reveal(),
            $calculator->reveal(),
            $taxCategoryRepository->reveal(),
            $deliveryManager,
            $this->eventDispatcher->reveal()
        );

        $this->action = new $this->actionClass(
            $tokenStorage->reveal(),
            $this->redisProphecy->reveal(),
            $doctrine->reveal(),
            $orderManager,
            $deliveryManager
        );
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
