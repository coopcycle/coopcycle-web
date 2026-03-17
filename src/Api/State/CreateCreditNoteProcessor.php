<?php

namespace AppBundle\Api\State;

use ApiPlatform\Doctrine\Orm\State\ItemProvider;
use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProcessorInterface;
use AppBundle\Api\Dto\CreditNoteInput;
use AppBundle\Entity\Sylius\Order;
use AppBundle\Sylius\Customer\CustomerInterface;
use AppBundle\Sylius\Promotion\Checker\Rule\IsCustomerRuleChecker;
use AppBundle\Sylius\Promotion\Checker\Rule\IsRestaurantRuleChecker;
use AppBundle\Sylius\Payment\Context as PaymentContext;
use Ramsey\Uuid\Uuid;
use AppBundle\Sylius\Promotion\Action\FixedDiscountPromotionActionCommand;
use Sylius\Component\Promotion\Factory\PromotionCouponFactoryInterface;
use Sylius\Component\Promotion\Model\PromotionAction;
use Sylius\Component\Promotion\Repository\PromotionCouponRepositoryInterface;
use Sylius\Component\Resource\Factory\FactoryInterface;

class CreateCreditNoteProcessor implements ProcessorInterface
{
    public function __construct(
        private ItemProvider $itemProvider,
        private ProcessorInterface $persistProcessor,
        private FactoryInterface $promotionFactory,
        private FactoryInterface $promotionRuleFactory,
        private PromotionCouponFactoryInterface $promotionCouponFactory,
        private PromotionCouponRepositoryInterface $promotionCouponRepository)
    {}

    /**
     * @param CreditNoteInput $data
     */
    public function process($data, Operation $operation, array $uriVariables = [], array $context = [])
    {
        /** @var Order */
        $order = $this->itemProvider->provide($operation, $uriVariables, $context);

        $promotion = $this->promotionFactory->createNew();
        $promotion->setName('Lorem ipsum'); // TODO Generate name automatically
        $promotion->setCouponBased(true);
        $promotion->setCode(Uuid::uuid4()->toString());
        $promotion->setPriority(1);

        $promotionAction = new PromotionAction();
        $promotionAction->setType(FixedDiscountPromotionActionCommand::TYPE);
        $promotionAction->setConfiguration(['amount' => $data->amount]);

        $promotion->addAction($promotionAction);

        /** @var CustomerInterface */
        $customer = $order->getCustomer();

        if ($customer->hasUser()) {
            $promotionRule = $this->promotionRuleFactory->createNew();
            $promotionRule->setType(IsCustomerRuleChecker::TYPE);
            $promotionRule->setConfiguration([
                'username' => $customer->getUser()->getUserIdentifier(),
            ]);

            $promotion->addRule($promotionRule);
        } else {
            // TODO Implement guest customer credit notes
        }

        $isRestaurantRule = $this->promotionRuleFactory->createNew();
        $isRestaurantRule->setType(IsRestaurantRuleChecker::TYPE);
        $isRestaurantRule->setConfiguration([
            'restaurant_id' => $order->getRestaurant()->getId(),
        ]);

        $promotion->addRule($isRestaurantRule);

        if (empty($data->couponCode)) {
            do {
                $hash = bin2hex(random_bytes(20));
                $code = strtoupper(substr($hash, 0, 6));
            } while (null !== $this->promotionCouponRepository->findOneBy(['code' => $code]));
        } else {
            $code = $data->couponCode;
        }

        $promotionCoupon = $this->promotionCouponFactory->createNew();
        $promotionCoupon->setCode($code);
        $promotionCoupon->setPerCustomerUsageLimit(1);

        $promotion->addCoupon($promotionCoupon);

        return $this->persistProcessor->process($promotion, $operation, $uriVariables, $context);
    }
}
