<?php

namespace AppBundle\Form\Checkout;

use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;

/**
 * The gift card code field on the basket page, sitting next to the coupon
 * field it is deliberately shaped like.
 *
 * Unmapped, and resolved in the controller rather than by a data
 * transformer, because a bad code has several distinct reasons -- expired,
 * already spent, meant for another restaurant -- and the customer needs to
 * be told which one.
 */
class CheckoutGiftCardType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder->add('code', TextType::class, [
            'label' => 'form.checkout.gift_card.label',
            'required' => false,
            'mapped' => false,
            'attr' => ['autocomplete' => 'off', 'autocapitalize' => 'characters'],
        ]);
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => null,
        ]);
    }
}
