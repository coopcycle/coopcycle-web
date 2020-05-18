<?php

namespace AppBundle\Form\Type;

use AppBundle\Form\Type\MoneyType;
use Sylius\Bundle\TaxationBundle\Form\Type\TaxCategoryChoiceType;
use Sylius\Component\Product\Resolver\ProductVariantResolverInterface;
use Sylius\Component\Taxation\Calculator\CalculatorInterface;
use Sylius\Component\Taxation\Repository\TaxCategoryRepositoryInterface;
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
    private $taxCategoryRepository;
    private $calculator;

    public function __construct(
        ProductVariantResolverInterface $variantResolver,
        TaxRateResolverInterface $taxRateResolver,
        TaxCategoryRepositoryInterface $taxCategoryRepository,
        CalculatorInterface $calculator)
    {
        $this->variantResolver = $variantResolver;
        $this->taxRateResolver = $taxRateResolver;
        $this->taxCategoryRepository = $taxCategoryRepository;
        $this->calculator = $calculator;
    }

    public function buildForm(FormBuilderInterface $builder, array $options)
    {
        $taxCategories = [];
        foreach ($this->taxCategoryRepository->findAll() as $taxCategory) {
            foreach ($taxCategory->getRates() as $taxRate) {
                $taxCategories[$taxCategory->getCode()][] = [
                    'amount' => $taxRate->getAmount(),
                ];
            }
        }

        $builder
            ->add('taxExcluded', MoneyType::class, [
                'mapped' => false,
                'label' => 'form.price_with_tax.tax_excl.label'
            ])
            ->add('taxIncluded', MoneyType::class, [
                'mapped' => false,
                'label' => 'form.price_with_tax.tax_incl.label'
            ])
            ->add('taxCategory', TaxCategoryChoiceType::class, [
                'mapped' => false,
                'label' => 'form.product.taxCategory.label',
                'attr' => [
                    'data-tax-categories' => json_encode($taxCategories),
                ]
            ]);

        $builder->addEventListener(FormEvents::POST_SET_DATA, function (FormEvent $event) {

            $form = $event->getForm();
            $product = $form->getParent()->getData();

            if (null !== $product->getId()) {

                $variant = $this->variantResolver->getVariant($product);
                $taxRate = $this->taxRateResolver->resolve($variant);

                $taxAmount = (int) $this->calculator->calculate($variant->getPrice(), $taxRate);

                $form->get('taxExcluded')->setData($variant->getPrice() - $taxAmount);
                $form->get('taxIncluded')->setData($variant->getPrice());
                $form->get('taxCategory')->setData($variant->getTaxCategory());
            }
        });
    }

    public function getBlockPrefix()
    {
        return 'coopcycle_price_with_tax';
    }
}
