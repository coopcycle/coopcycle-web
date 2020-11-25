<?php

namespace AppBundle\Form\Type;

use AppBundle\Entity\Sylius\TaxCategory;
use Doctrine\ORM\EntityRepository;
use Sylius\Bundle\TaxationBundle\Form\Type\TaxCategoryChoiceType as BaseTaxCategoryChoiceType;
use Sylius\Component\Product\Factory\ProductVariantFactoryInterface;
use Sylius\Component\Taxation\Resolver\TaxRateResolverInterface;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\ChoiceList\ArrayChoiceList;
use Symfony\Component\Form\ChoiceList\Loader\ChoiceLoaderInterface;
use Symfony\Component\OptionsResolver\Options;
use Symfony\Component\OptionsResolver\OptionsResolver;

class ProductTaxCategoryChoiceLoader implements ChoiceLoaderInterface
{
    private $taxCategoryRepository;
    private $country;
    private $legacyTaxes = true;

    private static $serviceTaxCategories = [
        'SERVICE',
        'SERVICE_TAX_EXEMPT',
    ];

    private static $otherTaxCategories = [
        'DRINK',
        'DRINK_ALCOHOL',
        'FOOD',
        'FOOD_TAKEAWAY',
        'JEWELRY',
        'BASE_STANDARD',
        'BASE_INTERMEDIARY',
        'BASE_REDUCED',
        'BASE_EXEMPT',
    ];

    public function __construct(
        EntityRepository $taxCategoryRepository,
        TaxRateResolverInterface $taxRateResolver,
        ProductVariantFactoryInterface $productVariantFactory,
        string $country,
        bool $legacyTaxes)
    {
        $this->taxCategoryRepository = $taxCategoryRepository;
        $this->taxRateResolver = $taxRateResolver;
        $this->productVariantFactory = $productVariantFactory;
        $this->country = $country;
        $this->legacyTaxes = $legacyTaxes;
    }

    /**
     * {@inheritdoc}
     */
    public function loadChoiceList($value = null)
    {
        $qb = $this->taxCategoryRepository->createQueryBuilder('c');
        $qb->andWhere($qb->expr()->notIn('c.code', self::$serviceTaxCategories));

        if ($this->legacyTaxes) {
            $qb->andWhere($qb->expr()->notIn('c.code', self::$otherTaxCategories));
        } else {
            $qb->andWhere($qb->expr()->in('c.code', self::$otherTaxCategories));
        }

        $categories = $qb->getQuery()->getResult();

        if ($this->legacyTaxes) {

            return new ArrayChoiceList($categories, $value);
        }

        // Remove tax categories when tax rate can't be resolved
        $categories = array_filter($categories, function (TaxCategory $c) {

            $variant = $this->productVariantFactory->createNew();
            $variant->setTaxCategory($c);

            $rate = $this->taxRateResolver->resolve($variant, [
                'country' => strtolower($this->country)
            ]);

            return $rate !== null;
        });

        return new ArrayChoiceList($categories, $value);
    }

    /**
     * {@inheritdoc}
     */
    public function loadChoicesForValues(array $values, $value = null)
    {
        // Optimize
        if (empty($values)) {
            return [];
        }

        return $this->loadChoiceList($value)->getChoicesForValues($values);
    }

    /**
     * {@inheritdoc}
     */
    public function loadValuesForChoices(array $choices, $value = null)
    {
        // Optimize
        if (empty($choices)) {
            return [];
        }

        return $this->loadChoiceList($value)->getValuesForChoices($choices);
    }
}
