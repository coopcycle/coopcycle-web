<?php

namespace AppBundle\Form\Sylius\Promotion;

use AppBundle\Entity\LocalBusiness;
use AppBundle\Entity\Sylius\Promotion;
use AppBundle\Form\Sylius\Promotion\EventSubscriber\IsRestaurantRuleSubscriber;
use Ramsey\Uuid\Uuid;
use Sylius\Bundle\PromotionBundle\Form\Type\PromotionCouponType;
use Sylius\Bundle\PromotionBundle\Form\Type\PromotionType;
use Sylius\Component\Promotion\Model\PromotionAction;
use Sylius\Component\Promotion\Repository\PromotionCouponRepositoryInterface;
use Sylius\Component\Resource\Factory\FactoryInterface;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\Form\FormEvents;
use Symfony\Component\Form\FormEvent;
use Symfony\Component\Form\FormError;
use Symfony\Component\Form\FormInterface;
use Symfony\Component\Form\Extension\Core\Type\CheckboxType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\Security\Core\Authorization\AuthorizationCheckerInterface;
use Symfony\Contracts\Translation\TranslatorInterface;

class RestaurantPromotionType extends AbstractType
{
    public function __construct(
        private FactoryInterface $promotionRuleFactory,
        private PromotionCouponRepositoryInterface $promotionCouponRepository,
        private TranslatorInterface $translator,
        private AuthorizationCheckerInterface $authorizationChecker)
    {}

    public function buildForm(FormBuilderInterface $builder, array $options)
    {
        // We remove some unused fields, or fields that we manage behind the scenes
        $builder->remove('description');
        $builder->remove('translations');
        $builder->remove('exclusive');
        $builder->remove('appliesToDiscounted');
        $builder->remove('priority');

        if ($this->authorizationChecker->isGranted('ROLE_DISPATCHER')) {
            $builder
                ->add('decrasePlatformFee', CheckboxType::class, [
                    'mapped' => false,
                    'required' => false,
                    'label' => 'form.offer_delivery.decrease_platform_fee.label',
                    'help' => 'form.offer_delivery.decrease_platform_fee.help',
                    'priority' => -1,
                ]);
        }

        // The "code" field is added via AddCodeFormSubscriber
        $builder->addEventListener(FormEvents::POST_SET_DATA, function (FormEvent $event) {
            $event->getForm()->remove('code');
        });

        // Add a "coupon" sub-form
        $builder->addEventListener(FormEvents::POST_SET_DATA, function (FormEvent $event) {

            $form = $event->getForm();
            $promotion = $event->getData();

            $couponData = null;

            if (null !== $promotion && null !== $promotion->getId()) {
                if ($promotion->isCouponBased()) {
                    $couponData = $promotion->getCoupons()->first();
                }
            }

            $form->add('coupon', PromotionCouponType::class, [
                'mapped' => false,
                'data' => $couponData,
            ]);

            // Add custom help option to coupon.code form
            $this->overrideOptions($form->get('coupon'), 'code', ['help' => 'form.offer_delivery.coupon_code.help']);
        });

        // Disable "couponBased" checkbox for existing promotions
        $builder->addEventListener(FormEvents::POST_SET_DATA, function (FormEvent $event) {

            $form = $event->getForm();
            $promotion = $event->getData();

            if (null !== $promotion && null !== $promotion->getId()) {
                $this->overrideOptions($form, 'couponBased', ['disabled' => true]);
            }
        });

        // This will add/remove the IsRestaurantRule promotion rule
        $builder->addEventSubscriber(
            new IsRestaurantRuleSubscriber($options['local_business'], $this->promotionRuleFactory)
        );

        // We move rules + actions to the bottom of the form
        $builder->addEventListener(FormEvents::POST_SET_DATA, function (FormEvent $event) {
            $this->moveTo($event->getForm(), 'rules', -2);
            $this->moveTo($event->getForm(), 'actions', -3);
        });

        $builder->addEventListener(FormEvents::SUBMIT, function (FormEvent $event) {

            $form = $event->getForm();
            $promotion = $event->getData();

            if (null === $promotion->getId()) {

                if ($promotion->isCouponBased()) {

                    $coupon = $form->get('coupon')->getData();

                    if ($exists = $this->promotionCouponRepository->findOneBy(['code' => $coupon->getCode()])) {
                        $message =
                            $this->translator->trans('form.offer_delivery.coupon_code.error.exists');
                        $form->get('coupon')->get('code')->addError(new FormError($message));

                        return;
                    }

                    $promotion->addCoupon($coupon);
                }

                $promotion->setCode(Uuid::uuid4()->toString());
                $promotion->setPriority(1);
            }
        });

        $builder->addEventListener(FormEvents::SUBMIT, function (FormEvent $event) {

            $form = $event->getForm();
            $promotion = $event->getData();

            $decreasePlatformFee = $form->has('decrasePlatformFee') ? $form->get('decrasePlatformFee')->getData() : false;

            foreach ($promotion->getActions() as $action) {
                $configuration = $action->getConfiguration();
                // TODO Fix typo s/decrase/decrease (needs Doctrine migration for existing actions)
                $configuration['decrase_platform_fee'] = $decreasePlatformFee;
                $action->setConfiguration($configuration);
            }
        });
    }

    private function overrideOptions(FormInterface $form, string $name, array $options)
    {
        $config = $form->get($name)->getConfig();
        $form->add($name, get_class($config->getType()->getInnerType()), array_merge($config->getOptions(), $options));
    }

    private function moveTo(FormInterface $form, string $name, int $priority)
    {
        $this->overrideOptions($form, $name, ['priority' => $priority]);
    }

    public function getParent(): string
    {
        return PromotionType::class;
    }

    public function configureOptions(OptionsResolver $resolver)
    {
        $resolver->setDefaults(array(
            'data_class' => Promotion::class,
        ));

        $resolver->setRequired('local_business');
        $resolver->setAllowedTypes('local_business', LocalBusiness::class);
    }
}

