<?php

namespace AppBundle\Form\Type;

use AppBundle\Entity\Sylius\TaxRate;
use AppBundle\Form\Type\MoneyType;
use AppBundle\Sylius\Product\LazyProductVariantResolverInterface;
use Sylius\Component\Taxation\Calculator\CalculatorInterface;
use Sylius\Component\Taxation\Resolver\TaxRateResolverInterface;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Exception\UnexpectedTypeException;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\Form\FormInterface;
use Symfony\Component\Form\FormEvents;
use Symfony\Component\Form\FormEvent;
use Symfony\Component\OptionsResolver\OptionsResolver;

class PriceWithTaxType extends AbstractType
{
    private $variantResolver;
    private $taxRateResolver;
    private $calculator;

    public function __construct(
        LazyProductVariantResolverInterface $variantResolver,
        TaxRateResolverInterface $taxRateResolver,
        CalculatorInterface $calculator,
        bool $taxIncl = true)
    {
        $this->variantResolver = $variantResolver;
        $this->taxRateResolver = $taxRateResolver;
        $this->calculator = $calculator;
        $this->taxIncl = $taxIncl;
    }

    public function buildForm(FormBuilderInterface $builder, array $options)
    {
        $builder
            ->add('taxExcluded', MoneyType::class, [
                'mapped' => false,
                'label' => 'form.price_with_tax.tax_excl.label'
            ])
            ->add('taxIncluded', MoneyType::class, [
                'mapped' => false,
                'label' => 'form.price_with_tax.tax_incl.label'
            ])
            ->add('taxCategory', ProductTaxCategoryChoiceType::class, [
                'mapped' => false,
                'label' => 'form.product.taxCategory.label',
            ]);

        $builder->addEventListener(FormEvents::POST_SET_DATA, function (FormEvent $event) use ($options) {

            $form = $event->getForm();
            $product = $form->getParent()->getData();

            $variant = null;

            if ($options['for_local_business_group']) {
                if (null !== $options['local_business_group']) {
                    $product = $form->getRoot()->getData();
                    $variant = $this->variantResolver->getVariantForBusinessRestaurantGroup($product, $options['local_business_group']);
                }
            } else {
                if ($product && null !== $product->getId()) {
                    $variant = $this->variantResolver->getVariant($product);
                }
            }

            if ($variant) {
                $rates = $this->taxRateResolver->resolveAll($variant);

                if (count($rates) > 0) {

                    $taxAmount = array_reduce(
                        $rates->toArray(),
                        fn($carry, $rate): int => $carry + (int) $this->calculator->calculate($variant->getPrice(), $rate),
                        0
                    );

                    $taxExcluded = $this->taxIncl ? ($variant->getPrice() - $taxAmount) : $variant->getPrice();
                    $taxIncluded = $this->taxIncl ? $variant->getPrice() : ($variant->getPrice() + $taxAmount);

                    $form->get('taxExcluded')->setData($taxExcluded);
                    $form->get('taxIncluded')->setData($taxIncluded);
                    $form->get('taxCategory')->setData($variant->getTaxCategory());

                } else {
                    if ($this->taxIncl) {
                        $form->get('taxExcluded')->setData(0);
                        $form->get('taxIncluded')->setData($variant->getPrice());
                    } else {
                        $form->get('taxExcluded')->setData($variant->getPrice());
                        $form->get('taxIncluded')->setData(0);
                    }
                }
            }
        });
    }

    public function getBlockPrefix()
    {
        return 'coopcycle_price_with_tax';
    }

    public function configureOptions(OptionsResolver $resolver)
    {
        $resolver->setDefaults(array(
            'for_local_business_group' => false,
            'local_business_group' => null,
        ));
    }
}
