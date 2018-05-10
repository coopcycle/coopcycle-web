<?php

namespace AppBundle\Form\MenuEditor;

use AppBundle\Entity\Sylius\ProductTaxon;
use Sylius\Component\Product\Model\ProductInterface;
use Sylius\Component\Product\Repository\ProductRepositoryInterface;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\CallbackTransformer;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\Form\FormInterface;
use Symfony\Component\Form\Extension\Core\Type\IntegerType;
use Symfony\Component\OptionsResolver\OptionsResolver;

class TaxonProductType extends AbstractType
{
    private $productRepository;

    public function __construct(ProductRepositoryInterface $productRepository)
    {
        $this->productRepository = $productRepository;
    }

    public function buildForm(FormBuilderInterface $builder, array $options)
    {
        $builder
            ->add('product', IntegerType::class)
            ->add('position', IntegerType::class);

        $builder
            ->get('product')
            ->addModelTransformer(new CallbackTransformer(
                function ($entity) {
                    if ($entity instanceof ProductInterface) {
                        return $entity->getId();
                    }
                },
                function ($id) {
                    if (!$id) {
                        return null;
                    }

                    return $this->productRepository->find($id);
                }
            ))
        ;
    }

    public function configureOptions(OptionsResolver $resolver)
    {
        $resolver->setDefaults(array(
            'data_class' => ProductTaxon::class,
        ));
    }
}
