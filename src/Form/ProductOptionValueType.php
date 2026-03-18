<?php

namespace AppBundle\Form;

use AppBundle\Entity\Sylius\Product;
use AppBundle\Entity\Sylius\ProductOptionValue;
use AppBundle\Form\Type\MoneyType;
use Doctrine\ORM\EntityManagerInterface;
use Sylius\Bundle\ProductBundle\Form\Type\ProductOptionValueTranslationType;
use Sylius\Bundle\ResourceBundle\Form\Type\ResourceTranslationsType;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\CallbackTransformer;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\Form\Exception\TransformationFailedException;
use Symfony\Component\Form\Extension\Core\Type\CheckboxType;
use Symfony\Component\Form\Extension\Core\Type\HiddenType;
use Symfony\Component\OptionsResolver\OptionsResolver;

class ProductOptionValueType extends AbstractType
{
    public function __construct(private EntityManagerInterface $entityManager)
    {}

    public function buildForm(FormBuilderInterface $builder, array $options)
    {
        $builder
            ->add('translations', ResourceTranslationsType::class, [
                'entry_type' => ProductOptionValueTranslationType::class,
            ])
            ->add('price', MoneyType::class, [
                'label' => 'form.product_option_value.price.label',
                'empty_data' => 0,
            ])
            ->add('product', HiddenType::class, [
                'label' => 'form.product_option_value.product.label',
                'required' => false,
            ])
            ->add('enabled', CheckboxType::class, [
                'label' => 'basics.enabled',
                'required' => false,
            ]);

        $builder->get('product')
            ->addModelTransformer(new CallbackTransformer(
                function ($product): string {
                    if (null === $product) {
                        return '';
                    }

                    return $product->getId();
                },
                function ($productId): ?Product {

                    if (!$productId) {
                        return null;
                    }

                    $product = $this->entityManager
                        ->getRepository(Product::class)
                        ->find($productId)
                    ;

                    if (null === $product) {
                        throw new TransformationFailedException(sprintf(
                            'Product "#%d" does not exist',
                            $productId
                        ));
                    }

                    return $product;
                }
            ))
        ;
    }

    public function configureOptions(OptionsResolver $resolver)
    {
        $resolver->setDefaults(array(
            'data_class' => ProductOptionValue::class,
        ));
    }
}
