<?php

namespace AppBundle\Form\Type;

use AppBundle\Entity\Sylius\TaxCategory;
use AppBundle\Entity\Sylius\TaxRate;
use AppBundle\Sylius\Taxation\Resolver\TaxRateResolver;
use Doctrine\ORM\EntityRepository;
use Sylius\Bundle\TaxationBundle\Form\Type\TaxCategoryChoiceType as BaseTaxCategoryChoiceType;
use Sylius\Component\Product\Factory\ProductVariantFactoryInterface;
use Sylius\Component\Resource\Repository\RepositoryInterface;
use Sylius\Component\Taxation\Resolver\TaxRateResolverInterface;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\OptionsResolver\Options;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Contracts\Translation\TranslatorInterface;

class ProductTaxCategoryChoiceType extends AbstractType
{
    /**
     * @param TaxRateResolver $taxRateResolver
     */
    public function __construct(
        private EntityRepository $taxCategoryRepository,
        private TranslatorInterface $translator,
        private TaxRateResolverInterface $taxRateResolver,
        private ProductVariantFactoryInterface $productVariantFactory,
        private string $country,
        private string $locale,
        private bool $legacyTaxes = true)
    {}

    public function configureOptions(OptionsResolver $resolver)
    {
        $resolver->setDefault('choice_loader', function (Options $options) {

            return new ProductTaxCategoryChoiceLoader(
                $this->taxCategoryRepository,
                $this->taxRateResolver,
                $this->productVariantFactory,
                $this->country,
                $this->legacyTaxes
            );
        });

        $resolver->setDefault('choice_label', function (?TaxCategory $taxCategory) {

            $variant = $this->productVariantFactory->createNew();
            $variant->setTaxCategory($taxCategory);

            $rates = $this->taxRateResolver->resolveAll($variant);

            if (count($rates) === 0) {
                return '';
            }

            // When multiple rates apply
            // Ex: Base › Standard rate (GST 5%, PST 7%)
            if (count($rates) > 1) {

                $ratesAsString = array_map(function (TaxRate $rate) {
                    return sprintf('%s %d%%', $this->translator->trans($rate->getName(), [], 'taxation'), $rate->getAmount() * 100);
                }, $rates->toArray());

                return sprintf('%s (%s)',
                    $this->translator->trans($taxCategory->getName(), [], 'taxation'),
                    implode(', ', $ratesAsString)
                );
            }

            $amount = array_reduce(
                $rates->toArray(),
                fn($carry, $rate) => $carry + $rate->getAmount(),
                0.0
            );

            $formatter = new \NumberFormatter($this->locale, \NumberFormatter::PERCENT);
            $formatter->setAttribute(\NumberFormatter::MAX_FRACTION_DIGITS, 2);

            return sprintf('%s (%s)',
                $this->translator->trans($taxCategory->getName(), [], 'taxation'),
                $formatter->format($amount)
            );
        });

        $resolver->setDefault('placeholder', 'form.product_tax_category_choice.placeholder');
    }

    public function getParent(): string
    {
        return BaseTaxCategoryChoiceType::class;
    }
}
