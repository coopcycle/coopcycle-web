<?php

namespace AppBundle\Validator;

use AppBundle\Entity\User;
use AppBundle\Entity\Restaurant;
use AppBundle\Entity\Sylius\Customer;
use AppBundle\Entity\Sylius\Order;
use AppBundle\LoopEat\Client as LoopEatClient;
use AppBundle\LoopEat\GuestCheckoutAwareAdapter as LoopEatAdapter;
use AppBundle\Validator\Constraints\LoopEatOrder as LoopEatOrderConstraint;
use AppBundle\Validator\Constraints\LoopEatOrderValidator;
use Prophecy\Argument;
use Prophecy\PhpUnit\ProphecyTrait;
use Psr\Log\NullLogger;
use Symfony\Component\ExpressionLanguage\ExpressionLanguage;
use Symfony\Component\HttpFoundation\Session\SessionInterface;
use Symfony\Component\Validator\Test\ConstraintValidatorTestCase;

class LoopEatOrderValidatorTest extends ConstraintValidatorTestCase
{
    use ProphecyTrait;

    protected $tokenStorage;
    protected $loopeatClient;

    public function setUp(): void
    {
        $this->tokenStorage = $this->prophesize(TokenStorageInterface::class);
        $this->loopeatClient = $this->prophesize(LoopEatClient::class);
        $this->session = $this->prophesize(SessionInterface::class);

        parent::setUp();
    }

    protected function createValidator()
    {
        return new LoopEatOrderValidator(
            $this->loopeatClient->reveal(),
            new NullLogger()
        );
    }

    public function testDoesNothingWhenLoopeatDisabled()
    {
        $customer = new Customer();

        $restaurant = new Restaurant();
        $restaurant->setLoopeatEnabled(false);

        $order = $this->prophesize(Order::class);
        $order
            ->getRestaurant()
            ->willReturn($restaurant);
        $order
            ->getCustomer()
            ->willReturn($customer);
        $order
            ->getReusablePackagingQuantity()
            ->willReturn(3);
        $order
            ->getReusablePackagingPledgeReturn()
            ->willReturn(0);
        $order
            ->isReusablePackagingEnabled()
            ->willReturn(true);

        $this->loopeatClient
            ->currentCustomer(Argument::type(LoopEatAdapter::class))
            ->willReturn(['loopeatBalance' => 2]);

        $constraint = new LoopEatOrderConstraint();

        $this->validator->validate($order->reveal(), $constraint);

        $this->assertNoViolation();
    }

    public function testInsufficientBalance()
    {
        $user = new User();

        $customer = new Customer();
        $customer->setUser($user);

        $restaurant = new Restaurant();
        $restaurant->setLoopeatEnabled(true);

        $order = $this->prophesize(Order::class);
        $order
            ->getRestaurant()
            ->willReturn($restaurant);
        $order
            ->getCustomer()
            ->willReturn($customer);
        $order
            ->getReusablePackagingQuantity()
            ->willReturn(3);
        $order
            ->getRequiredAmountForLoopeat()
            ->willReturn(1200);
        $order
            ->getReturnsAmountForLoopeat()
            ->willReturn(0);
        $order
            ->isReusablePackagingEnabled()
            ->willReturn(true);

        $this->loopeatClient
            ->currentCustomer(Argument::type(LoopEatAdapter::class))
            ->willReturn(['credits_count_cents' => 1000]);

        $constraint = new LoopEatOrderConstraint();
        $violations = $this->validator->validate($order->reveal(), $constraint);

        $this->buildViolation($constraint->insufficientBalance)
            ->atPath('property.path.reusablePackagingEnabled')
            ->setParameter('%count%', 200)
            ->assertRaised();
    }

    public function testReusablePackagingQuantityEqualsZero()
    {
        $user = new User();

        $customer = new Customer();
        $customer->setUser($user);

        $restaurant = new Restaurant();
        $restaurant->setLoopeatEnabled(true);

        $order = $this->prophesize(Order::class);
        $order
            ->getRestaurant()
            ->willReturn($restaurant);
        $order
            ->getCustomer()
            ->willReturn($customer);
        $order
            ->getReusablePackagingQuantity()
            ->willReturn(0);
        $order
            ->isReusablePackagingEnabled()
            ->willReturn(true);

        $this->loopeatClient
            ->currentCustomer(Argument::type(LoopEatAdapter::class))
            ->shouldNotBeCalled();

        $constraint = new LoopEatOrderConstraint();
        $violations = $this->validator->validate($order->reveal(), $constraint);

        $this->buildViolation($constraint->insufficientQuantity)
            ->atPath('property.path.reusablePackagingEnabled')
            ->assertRaised();
    }

    public function testInsufficientBalanceWithReturns()
    {
        $user = new User();

        $customer = new Customer();
        $customer->setUser($user);

        $restaurant = new Restaurant();
        $restaurant->setLoopeatEnabled(true);

        $order = $this->prophesize(Order::class);
        $order
            ->getRestaurant()
            ->willReturn($restaurant);
        $order
            ->getCustomer()
            ->willReturn($customer);
        $order
            ->getReusablePackagingQuantity()
            ->willReturn(4);
        $order
            ->getRequiredAmountForLoopeat()
            ->willReturn(1500);
        $order
            ->getReturnsAmountForLoopeat()
            ->willReturn(500);
        $order
            ->isReusablePackagingEnabled()
            ->willReturn(true);

        $this->loopeatClient
            ->currentCustomer(Argument::type(LoopEatAdapter::class))
            ->willReturn(['credits_count_cents' => 500]);

        $constraint = new LoopEatOrderConstraint();
        $violations = $this->validator->validate($order->reveal(), $constraint);

        $this->buildViolation($constraint->insufficientBalance)
            ->atPath('property.path.reusablePackagingEnabled')
            ->setParameter('%count%', 500)
            ->assertRaised();
    }

    public function testValid()
    {
        $user = new User();

        $customer = new Customer();
        $customer->setUser($user);

        $restaurant = new Restaurant();
        $restaurant->setLoopeatEnabled(true);

        $order = $this->prophesize(Order::class);
        $order
            ->getRestaurant()
            ->willReturn($restaurant);
        $order
            ->getCustomer()
            ->willReturn($customer);
        $order
            ->getReusablePackagingQuantity()
            ->willReturn(3);
        $order
            ->getRequiredAmountForLoopeat()
            ->willReturn(1000);
        $order
            ->getReturnsAmountForLoopeat()
            ->willReturn(0);
        $order
            ->isReusablePackagingEnabled()
            ->willReturn(true);

        $this->loopeatClient
            ->currentCustomer(Argument::type(LoopEatAdapter::class))
            ->willReturn(['credits_count_cents' => 1000]);

        $constraint = new LoopEatOrderConstraint();
        $violations = $this->validator->validate($order->reveal(), $constraint);

        $this->assertNoViolation();
    }
}
