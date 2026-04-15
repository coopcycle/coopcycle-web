<?php

namespace AppBundle\Form;

use ApiPlatform\Api\IriConverterInterface;
use AppBundle\Entity\Sylius\Product;
use AppBundle\Entity\Sylius\ProductOptionValue;
use AppBundle\Form\Type\MoneyType;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\EntityManagerInterface;
use Sylius\Bundle\ProductBundle\Form\Type\ProductOptionValueTranslationType;
use Sylius\Bundle\ResourceBundle\Form\Type\ResourceTranslationsType;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\CallbackTransformer;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\Form\Exception\TransformationFailedException;
use Symfony\Component\Form\Extension\Core\Type\CheckboxType;
use Symfony\Component\Form\Extension\Core\Type\CollectionType;
use Symfony\Component\Form\Extension\Core\Type\HiddenType;
use Symfony\Component\OptionsResolver\OptionsResolver;

class ProductOptionValueType extends AbstractType
{
    public function __construct(private EntityManagerInterface $entityManager,
        private IriConverterInterface $iriConverter)
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
            ->add('dependsOn', CollectionType::class, [
                'label' => 'form.product_option_value.depends_on.label',
                'entry_type' => HiddenType::class,
                'entry_options' => ['label' => false],
                'required' => false,
                'allow_add' => true,
                'allow_delete' => true,
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

        $builder->get('dependsOn')
            ->addModelTransformer(new CallbackTransformer(
                function ($optionsValues): array {
                    if (null === $optionsValues) {
                        return [];
                    }

                    return array_map(
                        fn ($optVal) => $this->iriConverter->getIriFromResource($optVal),
                        $optionsValues->toArray()
                    );
                },
                function (array $iris): Collection {

                    return new ArrayCollection(
                        array_map(fn ($iri) => $this->iriConverter->getResourceFromIri($iri), $iris)
                    );
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
