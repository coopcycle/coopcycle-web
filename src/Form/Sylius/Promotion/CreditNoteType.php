<?php

namespace AppBundle\Form\Sylius\Promotion;

use AppBundle\Entity\LocalBusiness;
use AppBundle\Form\Model\Promotion as PromotionData;
use AppBundle\Form\Type\MoneyType;
use AppBundle\Sylius\Promotion\Action\FixedDiscountPromotionActionCommand;
use AppBundle\Sylius\Promotion\Action\PercentageDiscountPromotionActionCommand;
use Symfony\Bridge\Doctrine\Form\Type\EntityType;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\Form\FormEvents;
use Symfony\Component\Form\FormEvent;
use Symfony\Component\Form\FormError;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\Form\Extension\Core\Type\HiddenType;
use Symfony\Component\Form\Extension\Core\Type\PercentType;
use Symfony\Component\Form\Extension\Core\Type\SearchType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Contracts\Translation\TranslatorInterface;
use Symfony\Component\Validator\Validation;
use Symfony\Component\Validator\Constraints;

class CreditNoteType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options)
    {
        $builder
            ->add('name', TextType::class, [
                'label' => 'form.credit_note.name.label',
                'help' => 'form.credit_note.name.help'
            ])
            ->add('type', ChoiceType::class, [
                'label' => 'form.credit_note.type.label',
                'choices' => [
                    'Fixed' => FixedDiscountPromotionActionCommand::TYPE,
                    'Percentage' => PercentageDiscountPromotionActionCommand::TYPE,
                ],
                'expanded' => true,
                'multiple' => false,
            ])
            ->add('amount', MoneyType::class, [
                'label' => 'form.credit_note.amount.label',
                'attr' => ['data-promotion-action-configuration-key' => 'amount']
            ])
            ->add('percentage', PercentType::class, [
                'label' => 'form.credit_note.percentage.label',
                'attr' => ['data-promotion-action-configuration-key' => 'percentage']
            ])
            ->add('username', SearchType::class, [
                'label' => 'form.credit_note.user_search.label',
                'help' => 'form.credit_note.user_search.help',
                'attr' => [
                    'placeholder' => 'search.users',
                    'data-search' => 'user',
                ],
            ])
            ->add('restaurant', EntityType::class, [
                'class' => LocalBusiness::class,
                'choice_label' => 'name',
                'help' => 'form.credit_note.restaurant.help',
                'required' => false,
            ])
            ->add('couponCode', TextType::class, [
                'label' => 'sylius.ui.code',
                'help' => 'form.offer_delivery.coupon_code.help',
            ]);
    }

    public function configureOptions(OptionsResolver $resolver)
    {
        $resolver->setDefaults(array(
            'data_class' => PromotionData::class,
        ));
    }
}
