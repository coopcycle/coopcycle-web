<?php

namespace AppBundle\Sylius\Promotion\Checker\Rule;

use AppBundle\Entity\Sylius\OrderRepository;
use AppBundle\Sylius\Order\OrderInterface;
use Sylius\Component\Promotion\Checker\Rule\RuleCheckerInterface;
use Sylius\Component\Promotion\Model\PromotionSubjectInterface;
use Webmozart\Assert\Assert;

/**
 * Restricts a coupon to a customer's first paid order -- used for the
 * referral program's "welcome" coupon, so it stays redeemable only on the
 * order that is supposed to trigger the referrer's reward, even if the
 * customer abandons their first attempt and comes back later.
 */
class IsFirstOrderRuleChecker implements RuleCheckerInterface
{
    const TYPE = 'is_first_order';

    public function __construct(private readonly OrderRepository $orderRepository)
    {
    }

    /**
     * {@inheritdoc}
     */
    public function isEligible(PromotionSubjectInterface $subject, array $configuration): bool
    {
        Assert::isInstanceOf($subject, OrderInterface::class);

        $customer = $subject->getCustomer();

        if (null === $customer) {
            return false;
        }

        return 0 === $this->orderRepository->countPaidOrdersByCustomer($customer, $subject);
    }
}
