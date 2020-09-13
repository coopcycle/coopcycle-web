<?php

namespace AppBundle\Validator;

use AppBundle\Entity\ApiUser;
use AppBundle\Entity\Restaurant;
use AppBundle\Entity\Sylius\Order;
use AppBundle\LoopEat\Client as LoopEatClient;
use AppBundle\Validator\Constraints\LoopEatOrder as LoopEatOrderConstraint;
use AppBundle\Validator\Constraints\LoopEatOrderValidator;
use Prophecy\Argument;
use Prophecy\PhpUnit\ProphecyTrait;
use Psr\Log\NullLogger;
use Symfony\Component\ExpressionLanguage\ExpressionLanguage;
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
        $customer = new ApiUser();

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

        $this->loopeatClient->currentCustomer($customer)
            ->willReturn(['loopeatBalance' => 2]);

        $constraint = new LoopEatOrderConstraint();

        $this->validator->validate($order->reveal(), $constraint);

        $this->assertNoViolation();
    }

    public function testInsufficientBalance()
    {
        $customer = new ApiUser();

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
            ->getReusablePackagingPledgeReturn()
            ->willReturn(0);
        $order
            ->isReusablePackagingEnabled()
            ->willReturn(true);

        $this->loopeatClient->currentCustomer($customer)
            ->willReturn(['loopeatBalance' => 2]);

        $constraint = new LoopEatOrderConstraint();
        $violations = $this->validator->validate($order->reveal(), $constraint);

        $this->buildViolation($constraint->insufficientBalance)
            ->atPath('property.path.reusablePackagingEnabled')
            ->setParameter('%count%', 1)
            ->assertRaised();
    }

    public function testReusablePackagingQuantityEqualsZero()
    {
        $customer = new ApiUser();

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

        $this->loopeatClient->currentCustomer($customer)
            ->shouldNotBeCalled();

        $constraint = new LoopEatOrderConstraint();
        $violations = $this->validator->validate($order->reveal(), $constraint);

        $this->buildViolation($constraint->insufficientQuantity)
            ->atPath('property.path.reusablePackagingEnabled')
            ->assertRaised();
    }

    public function testInsufficientBalanceWithPledgeReturn()
    {
        $customer = new ApiUser();

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
            ->getReusablePackagingPledgeReturn()
            ->willReturn(1);
        $order
            ->isReusablePackagingEnabled()
            ->willReturn(true);

        $this->loopeatClient->currentCustomer($customer)
            ->willReturn(['loopeatBalance' => 2]);

        $constraint = new LoopEatOrderConstraint();
        $violations = $this->validator->validate($order->reveal(), $constraint);

        $this->buildViolation($constraint->insufficientBalance)
            ->atPath('property.path.reusablePackagingEnabled')
            ->setParameter('%count%', 1)
            ->assertRaised();
    }

    public function testValid()
    {
        $customer = new ApiUser();

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
            ->getReusablePackagingPledgeReturn()
            ->willReturn(0);
        $order
            ->isReusablePackagingEnabled()
            ->willReturn(true);

        $this->loopeatClient->currentCustomer($customer)
            ->willReturn(['loopeatBalance' => 3]);

        $constraint = new LoopEatOrderConstraint();
        $violations = $this->validator->validate($order->reveal(), $constraint);

        $this->assertNoViolation();
    }
}
