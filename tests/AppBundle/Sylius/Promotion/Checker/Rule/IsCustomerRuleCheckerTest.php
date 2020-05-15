<?php

namespace Tests\AppBundle\Sylius\Promotion\Checker\Rule;

use AppBundle\Sylius\Promotion\Checker\Rule\IsCustomerRuleChecker;
use PHPUnit\Framework\TestCase;
use Prophecy\PhpUnit\ProphecyTrait;
use Sylius\Component\Promotion\Model\PromotionSubjectInterface;
use Symfony\Component\Security\Core\Authentication\Token\Storage\TokenStorageInterface;
use Symfony\Component\Security\Core\Authentication\Token\TokenInterface;
use Symfony\Component\Security\Core\User\UserInterface;

class IsCustomerRuleCheckerTest extends TestCase
{
    use ProphecyTrait;

    private $lazyVariantResolver;
    private $variantFactory;
    private $defaultVariantResolver;

    public function setUp(): void
    {
        $this->tokenStorage = $this->prophesize(TokenStorageInterface::class);

        $this->ruleChecker = new IsCustomerRuleChecker(
            $this->tokenStorage->reveal()
        );
    }

    public function testIsEligibleWithoutCustomer()
    {
        $order = $this->prophesize(PromotionSubjectInterface::class);

        $configuration = [];

        $this->assertTrue($this->ruleChecker->isEligible($order->reveal(), $configuration));
    }

    public function testIsEligibleWithSameCustomer()
    {
        $order = $this->prophesize(PromotionSubjectInterface::class);

        $configuration = [
            'username' => 'john'
        ];

        $user = $this->prophesize(UserInterface::class);
        $user
            ->getUsername()
            ->willReturn('john');

        $token = $this->prophesize(TokenInterface::class);
        $token
            ->getUser()
            ->willReturn($user->reveal());

        $this->tokenStorage
            ->getToken()
            ->willReturn($token->reveal());

        $this->assertTrue($this->ruleChecker->isEligible($order->reveal(), $configuration));
    }

    public function testIsEligibleWithDifferentCustomer()
    {
        $order = $this->prophesize(PromotionSubjectInterface::class);

        $configuration = [
            'username' => 'john'
        ];

        $user = $this->prophesize(UserInterface::class);
        $user
            ->getUsername()
            ->willReturn('jane');

        $token = $this->prophesize(TokenInterface::class);
        $token
            ->getUser()
            ->willReturn($user->reveal());

        $this->tokenStorage
            ->getToken()
            ->willReturn($token->reveal());

        $this->assertFalse($this->ruleChecker->isEligible($order->reveal(), $configuration));
    }
}
