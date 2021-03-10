<?php

namespace AppBundle\Form\Model;

use AppBundle\Entity\LocalBusiness;
use AppBundle\Sylius\Promotion\Action\FixedDiscountPromotionActionCommand;
use AppBundle\Sylius\Promotion\Action\PercentageDiscountPromotionActionCommand;
use AppBundle\Sylius\Promotion\Checker\Rule\IsCustomerRuleChecker;
use AppBundle\Sylius\Promotion\Checker\Rule\IsRestaurantRuleChecker;
use Ramsey\Uuid\Uuid;
use Sylius\Component\Resource\Factory\FactoryInterface;
use Sylius\Component\Promotion\Factory\PromotionCouponFactoryInterface;
use Sylius\Component\Promotion\Model\PromotionAction;
use Sylius\Component\Promotion\Repository\PromotionCouponRepositoryInterface;

class Promotion
{
    public $name;
    public $type = FixedDiscountPromotionActionCommand::TYPE;
    public $amount;
    public $percentage;
    public $username;
    public $restaurant;
    public $couponCode;

    public function toPromotion(
        FactoryInterface $promotionFactory,
        FactoryInterface $promotionRuleFactory,
        PromotionCouponRepositoryInterface $promotionCouponRepository,
        PromotionCouponFactoryInterface $promotionCouponFactory)
    {
        $promotion = $promotionFactory->createNew();
        $promotion->setName($this->name);
        $promotion->setCouponBased(true);
        $promotion->setCode(Uuid::uuid4()->toString());
        $promotion->setPriority(1);

        $promotionAction = new PromotionAction();
        $promotionAction->setType($this->type);

        $promotionActionConfiguration = [];
        switch ($this->type) {
            case FixedDiscountPromotionActionCommand::TYPE:
                $promotionActionConfiguration = ['amount' => $this->amount];
                break;
            case PercentageDiscountPromotionActionCommand::TYPE:
                $promotionActionConfiguration = ['percentage' => $this->percentage];
                break;
        }
        $promotionAction->setConfiguration($promotionActionConfiguration);

        $promotion->addAction($promotionAction);

        if (!empty($this->username)) {

            $promotionRule = $promotionRuleFactory->createNew();
            $promotionRule->setType(IsCustomerRuleChecker::TYPE);
            $promotionRule->setConfiguration([
                'username' => $this->username
            ]);

            $promotion->addRule($promotionRule);
        }

        if ($this->restaurant instanceof LocalBusiness) {

            $isRestaurantRule = $promotionRuleFactory->createNew();
            $isRestaurantRule->setType(IsRestaurantRuleChecker::TYPE);
            $isRestaurantRule->setConfiguration([
                'restaurant_id' => $this->restaurant->getId()
            ]);

            $promotion->addRule($isRestaurantRule);
        }

        if (empty($this->couponCode)) {

            do {
                $hash = bin2hex(random_bytes(20));
                $code = strtoupper(substr($hash, 0, 6));
            } while (null !== $promotionCouponRepository->findOneBy(['code' => $code]));

        } else {
            $code = $this->couponCode;
        }

        $promotionCoupon = $promotionCouponFactory->createNew();
        $promotionCoupon->setCode($code);
        $promotionCoupon->setPerCustomerUsageLimit(1);

        $promotion->addCoupon($promotionCoupon);

        return $promotion;
    }
}
