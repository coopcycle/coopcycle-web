<?php


namespace AppBundle\Form\Extension;

use Sylius\Bundle\PromotionBundle\Form\Type\PromotionCouponType;
use Symfony\Component\Form\AbstractTypeExtension;
use Symfony\Component\Form\Extension\Core\Type\IntegerType;
use Symfony\Component\Form\FormBuilderInterface;

final class PromotionCouponTypeExtension extends AbstractTypeExtension
{
    /**
     * {@inheritdoc}
     */
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('perCustomerUsageLimit', IntegerType::class, [
                'label' => 'sylius.form.promotion_coupon.per_customer_usage_limit',
                'required' => false,
            ])
        ;
    }

    public static function getExtendedTypes(): iterable
    {
        return [
            PromotionCouponType::class
        ];
    }
}
