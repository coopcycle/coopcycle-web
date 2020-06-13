<?php

namespace AppBundle\Form\Type;

use AppBundle\Entity\Sylius\TaxCategory;
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
    /** @var RepositoryInterface */
    private $taxCategoryRepository;
    private $translator;
    private $country;

    public function __construct(
        RepositoryInterface $taxCategoryRepository,
        TranslatorInterface $translator,
        TaxRateResolverInterface $taxRateResolver,
        ProductVariantFactoryInterface $productVariantFactory,
        string $country)
    {
        $this->taxCategoryRepository = $taxCategoryRepository;
        $this->translator = $translator;
        $this->taxRateResolver = $taxRateResolver;
        $this->productVariantFactory = $productVariantFactory;
        $this->country = $country;
    }

    public function configureOptions(OptionsResolver $resolver)
    {
        $resolver->setDefault('choices', function (Options $options) {

            $qb = $this->taxCategoryRepository->createQueryBuilder('c');
            $qb->andWhere($qb->expr()->notIn('c.code', ['SERVICE', 'SERVICE_TAX_EXEMPT']));

            if ('ca' === $this->country) {
                $qb->andWhere($qb->expr()->in('c.code', ['FOOD', 'DRINK_ALCOHOL', 'JEWELRY']));
            } else {
                $qb->andWhere($qb->expr()->notIn('c.code', ['FOOD', 'DRINK_ALCOHOL', 'JEWELRY']));
            }

            return $qb->getQuery()->getResult();
        });

        $resolver->setDefault('choice_label', function (?TaxCategory $taxCategory) {

            $variant = $this->productVariantFactory->createNew();
            $variant->setTaxCategory($taxCategory);

            $rate = $this->taxRateResolver->resolve($variant);

            return sprintf('%s (%d%%)',
                $this->translator->trans($taxCategory->getName(), [], 'taxation'),
                $rate->getAmount() * 100
            );
        });

        $resolver->setDefault('placeholder', 'form.product_tax_category_choice.placeholder');
    }

    public function getParent(): string
    {
        return BaseTaxCategoryChoiceType::class;
    }
}
