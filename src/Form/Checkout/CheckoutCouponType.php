<?php

namespace AppBundle\Form\Checkout;

use AppBundle\Entity\Sylius\Order;
use Sylius\Bundle\PromotionBundle\Form\Type\PromotionCouponToCodeType;
use Sylius\Bundle\PromotionBundle\Validator\Constraints\PromotionSubjectCoupon;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\NumberType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;

class CheckoutCouponType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options)
    {
        $builder
            ->add('promotionCoupon', PromotionCouponToCodeType::class, [
                'label' => 'form.checkout_address.promotion_coupon.label',
                'required' => false,
            ])
            ;
    }

    public function configureOptions(OptionsResolver $resolver)
    {
        $resolver->setDefaults(array(
            'data_class' => Order::class,
            'constraints' => [
                new PromotionSubjectCoupon()
            ]
        ));
    }
}
