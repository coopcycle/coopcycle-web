<?php

namespace AppBundle\Form\Type;

use Sylius\Bundle\TaxationBundle\Form\Type\TaxCategoryChoiceType as BaseTaxCategoryChoiceType;
use Sylius\Component\Resource\Repository\RepositoryInterface;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\OptionsResolver\Options;
use Symfony\Component\OptionsResolver\OptionsResolver;

class ProductTaxCategoryChoiceType extends AbstractType
{
    /** @var RepositoryInterface */
    private $taxCategoryRepository;

    public function __construct(RepositoryInterface $taxCategoryRepository)
    {
        $this->taxCategoryRepository = $taxCategoryRepository;
    }

    public function configureOptions(OptionsResolver $resolver)
    {
        $resolver->setDefault('choices', function (Options $options) {
            $qb = $this->taxCategoryRepository->createQueryBuilder('c');
            $qb->add('where', $qb->expr()->notIn('c.code', ['SERVICE', 'SERVICE_TAX_EXEMPT']));

            return $qb->getQuery()->getResult();
        });
    }

    public function getParent(): string
    {
        return BaseTaxCategoryChoiceType::class;
    }
}
