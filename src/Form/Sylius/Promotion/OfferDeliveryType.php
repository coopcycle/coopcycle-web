<?php

namespace AppBundle\Form\Sylius\Promotion;

use AppBundle\Entity\LocalBusiness;
use AppBundle\Entity\Sylius\Promotion;
use AppBundle\Sylius\Promotion\Action\DeliveryPercentageDiscountPromotionActionCommand;
use AppBundle\Sylius\Promotion\Checker\Rule\IsRestaurantRuleChecker;
use Ramsey\Uuid\Uuid;
use Sylius\Bundle\PromotionBundle\Form\Type\PromotionCouponType;
use Sylius\Component\Promotion\Model\PromotionInterface;
use Sylius\Component\Promotion\Model\PromotionCoupon;
use Sylius\Component\Promotion\Model\PromotionAction;
use Sylius\Component\Promotion\Repository\PromotionCouponRepositoryInterface;
use Sylius\Component\Resource\Factory\FactoryInterface;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\Form\FormEvents;
use Symfony\Component\Form\FormEvent;
use Symfony\Component\Form\FormError;
use Symfony\Component\Form\Extension\Core\Type\CheckboxType;
use Symfony\Component\Form\Extension\Core\Type\HiddenType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\Security\Core\Authorization\AuthorizationCheckerInterface;
use Symfony\Contracts\Translation\TranslatorInterface;

class OfferDeliveryType extends AbstractType
{
    public function __construct(
        private FactoryInterface $promotionRuleFactory,
        private PromotionCouponRepositoryInterface $promotionCouponRepository,
        private TranslatorInterface $translator,
        private AuthorizationCheckerInterface $authorizationChecker)
    {}

    public function buildForm(FormBuilderInterface $builder, array $options)
    {
        $builder
            ->add('coupon', PromotionCouponType::class, [
                'mapped' => false,
                'label' => false,
            ]);

        // Set data in sub form
        $builder->addEventListener(FormEvents::POST_SET_DATA, function (FormEvent $event) {

            $form = $event->getForm();
            $data = $event->getData();

            $form->get('coupon')->setData($event->getData());

        });

        // Add custom help option to coupon.code form
        // Use POST_SET_DATA because AddCodeFormSubscriber uses PRE_SET_DATA
        $builder->addEventListener(FormEvents::POST_SET_DATA, function (FormEvent $event) {

            $form = $event->getForm();
            $data = $event->getData();

            $couponForm = $form->get('coupon');

            $config = $couponForm->get('code')->getConfig();
            $options = $config->getOptions();

            $options['help'] =
                $this->translator->trans('form.offer_delivery.coupon_code.help');

            $couponForm->add('code', get_class($config->getType()->getInnerType()), $options);
        });

        $builder->addEventListener(FormEvents::POST_SET_DATA, function (FormEvent $event) {

            $form = $event->getForm();
            $data = $event->getData();

            if (null === $data) {
                return;
            }

            if ($this->authorizationChecker->isGranted('ROLE_DISPATCHER')) {

                $promotion = $data->getPromotion();

                $isChecked = false;

                foreach ($promotion->getActions() as $action) {
                    if ($action->getType() === DeliveryPercentageDiscountPromotionActionCommand::TYPE) {
                        $configuration = $action->getConfiguration();
                        $isChecked = $configuration['decrase_platform_fee'];
                        break;
                    }
                }

                // TODO Fix typo
                $form
                    ->add('decrasePlatformFee', CheckboxType::class, [
                        'mapped' => false,
                        'required' => false,
                        'label' => 'form.offer_delivery.decrease_platform_fee.label',
                        'help' => 'form.offer_delivery.decrease_platform_fee.help',
                        'data' => $isChecked,
                    ]);
            }

        });

        $builder->addEventListener(FormEvents::SUBMIT, function (FormEvent $event) use ($options) {

            $form = $event->getForm();
            $coupon = $form->get('coupon')->getData();

            $decreasePlatformFee = $form->has('decrasePlatformFee') ? $form->get('decrasePlatformFee')->getData() : false;

            if (null === $coupon->getId()) {
                if ($exists = $this->promotionCouponRepository->findOneBy(['code' => $coupon->getCode()])) {
                    $message =
                        $this->translator->trans('form.offer_delivery.coupon_code.error.exists');
                    $form->get('coupon')->get('code')->addError(new FormError($message));

                    return;
                }
            }

            $promotion = $this->getOrCreatePromotion($coupon, $options['local_business'], $decreasePlatformFee);

            if (null !== $promotion->getId()) {
                foreach ($promotion->getActions() as $action) {
                    if ($action->getType() === DeliveryPercentageDiscountPromotionActionCommand::TYPE) {
                        $configuration = $action->getConfiguration();
                        $configuration['decrase_platform_fee'] = $decreasePlatformFee;
                        $action->setConfiguration($configuration);
                        break;
                    }
                }
            } else {
                $promotion->addCoupon($coupon);
            }

        });
    }

    private function getOrCreatePromotion(PromotionCoupon $coupon, LocalBusiness $restaurant, bool $decreasePlatformFee = false): PromotionInterface
    {
        if (null !== $coupon->getPromotion()) {
            return $coupon->getPromotion();
        }

        foreach ($restaurant->getPromotions() as $p) {
            foreach ($p->getActions() as $action) {
                if ($action->getType() === DeliveryPercentageDiscountPromotionActionCommand::TYPE) {
                    $configuration = $action->getConfiguration();
                    if ($configuration['percentage'] === 1.0) {
                        return $p;
                    }
                }
            }
        }

        $promotion = new Promotion();

        // FIXME Unrelated translation key
        $promotion->setName($this->translator->trans('promotions.heading.free_delivery'));
        $promotion->setCouponBased(true);

        $promotion->setCode(Uuid::uuid4()->toString());
        $promotion->setPriority(1);

        $isRestaurantRule = $this->promotionRuleFactory->createNew();
        $isRestaurantRule->setType(IsRestaurantRuleChecker::TYPE);
        $isRestaurantRule->setConfiguration([
            'restaurant_id' => $restaurant->getId()
        ]);

        $promotion->addRule($isRestaurantRule);

        $promotionAction = new PromotionAction();
        $promotionAction->setType(DeliveryPercentageDiscountPromotionActionCommand::TYPE);
        $promotionAction->setConfiguration([
            'percentage' => 1.0,
            'decrase_platform_fee' => $decreasePlatformFee,
        ]);

        $promotion->addAction($promotionAction);

        return $promotion;
    }

    public function configureOptions(OptionsResolver $resolver)
    {
        $resolver->setDefaults(array(
            'data_class' => PromotionCoupon::class,
        ));

        $resolver->setRequired('local_business');
        $resolver->setAllowedTypes('local_business', LocalBusiness::class);
    }
}
