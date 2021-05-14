<?php

namespace AppBundle\Form\Sylius\Promotion;

use AppBundle\Entity\LocalBusiness;
use AppBundle\Form\Type\MoneyType;
use AppBundle\Sylius\Promotion\Action\FixedDiscountPromotionActionCommand;
use AppBundle\Sylius\Promotion\Action\PercentageDiscountPromotionActionCommand;
use AppBundle\Sylius\Promotion\Checker\Rule\IsRestaurantRuleChecker;
use AppBundle\Sylius\Promotion\Checker\Rule\IsItemsTotalAboveRuleChecker;
use Ramsey\Uuid\Uuid;
use Sylius\Bundle\PromotionBundle\Form\Type\PromotionCouponType;
use Sylius\Component\Promotion\Model\Promotion;
use Sylius\Component\Promotion\Model\PromotionAction;
use Sylius\Component\Promotion\Repository\PromotionCouponRepositoryInterface;
use Sylius\Component\Resource\Factory\FactoryInterface;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\Form\FormEvents;
use Symfony\Component\Form\FormEvent;
use Symfony\Component\Form\FormError;
use Symfony\Component\Form\Extension\Core\Type\HiddenType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\Extension\Core\Type\IntegerType;
use Symfony\Component\Form\Extension\Core\Type\DateTimeType;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Contracts\Translation\TranslatorInterface;

class ItemsTotalBasedPromotionType extends AbstractType
{
    private $promotionRuleFactory;
    private $promotionCouponRepository;
    private $translator;

    public function __construct(
        FactoryInterface $promotionRuleFactory,
        PromotionCouponRepositoryInterface $promotionCouponRepository,
        TranslatorInterface $translator)
    {
        $this->promotionRuleFactory = $promotionRuleFactory;
        $this->promotionCouponRepository = $promotionCouponRepository;
        $this->translator = $translator;
    }

    public function buildForm(FormBuilderInterface $builder, array $options)
    {
        $builder
            ->add('name', TextType::class, [
                'mapped' => false,
                'label' => 'form.credit_note.name.label',
                'help' => 'form.credit_note.name.help'
            ])
            ->add('itemsTotal', MoneyType::class, [
                'mapped' => false,
                'label' => 'form.items_total_promotion.items_total.label',
            ])
            ->add('fixedDiscountAmount', MoneyType::class, [
                'mapped' => false,
                'label' => 'form.items_total_promotion.fixed_discount_amount.label',
            ])
            ->add('usageLimit', IntegerType::class, [
                'label' => 'sylius.form.promotion.usage_limit',
                'required' => false,
            ])
            ->add('startsAt', DateTimeType::class, [
                'label' => 'sylius.form.promotion.starts_at',
                'date_widget' => 'single_text',
                'time_widget' => 'single_text',
                'required' => false,
            ])
            ->add('endsAt', DateTimeType::class, [
                'label' => 'sylius.form.promotion.ends_at',
                'date_widget' => 'single_text',
                'time_widget' => 'single_text',
                'required' => false,
            ])
            ;

        $builder->addEventListener(FormEvents::SUBMIT, function (FormEvent $event) use ($options) {

            $form = $event->getForm();
            $promotion = $event->getData();

            if (null === $promotion->getId()) {

                $name = $form->get('name')->getData();
                $itemsTotal = $form->get('itemsTotal')->getData();
                $fixedDiscountAmount = $form->get('fixedDiscountAmount')->getData();

                $promotion->setName($name);
                $promotion->setCouponBased(false);

                $promotion->setCode(Uuid::uuid4()->toString());
                $promotion->setPriority(1);

                $isRestaurantRule = $this->promotionRuleFactory->createNew();
                $isRestaurantRule->setType(IsRestaurantRuleChecker::TYPE);
                $isRestaurantRule->setConfiguration([
                    'restaurant_id' => $options['local_business']->getId()
                ]);
                $promotion->addRule($isRestaurantRule);

                $isItemsTotalAboveRule = $this->promotionRuleFactory->createNew();
                $isItemsTotalAboveRule->setType(IsItemsTotalAboveRuleChecker::TYPE);
                $isItemsTotalAboveRule->setConfiguration([
                    'amount' => $itemsTotal
                ]);
                $promotion->addRule($isItemsTotalAboveRule);

                $promotionAction = new PromotionAction();
                $promotionAction->setType(FixedDiscountPromotionActionCommand::TYPE);
                $promotionAction->setConfiguration([
                    'amount' => $fixedDiscountAmount,
                    'decrase_platform_fee' => false,
                ]);

                $promotion->addAction($promotionAction);
            }
        });
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
