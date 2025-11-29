<?php

namespace Tests\AppBundle\Sylius\Promotion\Checker\Rule;

use AppBundle\Sylius\Promotion\Checker\Rule\IsCustomerRuleChecker;
use PHPUnit\Framework\TestCase;
use Prophecy\PhpUnit\ProphecyTrait;
use Sylius\Component\Promotion\Model\PromotionSubjectInterface;
use Symfony\Component\Security\Core\Security;
use Symfony\Component\Security\Core\User\UserInterface;

class IsCustomerRuleCheckerTest extends TestCase
{
    use ProphecyTrait;

    private $lazyVariantResolver;
    private $variantFactory;
    private $defaultVariantResolver;

    public function setUp(): void
    {
        $this->security = $this->prophesize(Security::class);

        $this->ruleChecker = new IsCustomerRuleChecker(
            $this->security->reveal()
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
            ->getUserIdentifier()
            ->willReturn('john');

        $this->security
            ->getUser()
            ->willReturn($user->reveal());

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
            ->getUserIdentifier()
            ->willReturn('jane');

        $this->security
            ->getUser()
            ->willReturn($user->reveal());

        $this->assertFalse($this->ruleChecker->isEligible($order->reveal(), $configuration));
    }
}
