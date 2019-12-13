<?php

namespace AppBundle\Form;

use AppBundle\Entity\ReusablePackaging;
use AppBundle\Entity\Sylius\Product;
use AppBundle\Entity\Sylius\ProductOption;
use AppBundle\Enum\Allergen;
use AppBundle\Enum\RestrictedDiet;
use Ramsey\Uuid\Uuid;
use Sylius\Bundle\TaxationBundle\Form\Type\TaxCategoryChoiceType;
use Sylius\Component\Locale\Provider\LocaleProviderInterface;
use Sylius\Component\Product\Factory\ProductVariantFactoryInterface;
use Sylius\Component\Product\Model\ProductAttributeValue;
use Sylius\Component\Product\Resolver\ProductVariantResolverInterface;
use Sylius\Component\Resource\Factory\FactoryInterface;
use Sylius\Component\Resource\Repository\RepositoryInterface;
use Symfony\Bridge\Doctrine\Form\Type\EntityType;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\Form\FormError;
use Symfony\Component\Form\FormEvents;
use Symfony\Component\Form\FormEvent;
use Symfony\Component\Form\FormInterface;
use Symfony\Component\Form\Extension\Core\Type\CheckboxType;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\Form\Extension\Core\Type\CollectionType;
use Symfony\Component\Form\Extension\Core\Type\MoneyType;
use Symfony\Component\Form\Extension\Core\Type\SubmitType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\Extension\Core\Type\TextareaType;
use Symfony\Component\Form\Extension\Core\Type\NumberType;
use Symfony\Component\OptionsResolver\Options;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\Translation\TranslatorInterface;

class ProductType extends AbstractType
{
    private $variantFactory;
    private $variantResolver;
    private $productAttributeRepository;
    private $productAttributeValueFactory;
    private $localeProvider;
    private $hasChangedName = false;

    public function __construct(
        ProductVariantFactoryInterface $variantFactory,
        ProductVariantResolverInterface $variantResolver,
        RepositoryInterface $productAttributeRepository,
        FactoryInterface $productAttributeValueFactory,
        LocaleProviderInterface $localeProvider,
        TranslatorInterface $translator)
    {
        $this->variantFactory = $variantFactory;
        $this->variantResolver = $variantResolver;
        $this->productAttributeRepository = $productAttributeRepository;
        $this->productAttributeValueFactory = $productAttributeValueFactory;
        $this->localeProvider = $localeProvider;
        $this->translator = $translator;
    }

    public function buildForm(FormBuilderInterface $builder, array $options)
    {
        $builder
            ->add('name', TextType::class, [
                'label' => 'form.product.name.label'
            ])
            ->add('description', TextareaType::class, [
                'required' => false,
                'label' => 'form.product.description.label'
            ])
            ->add('enabled', CheckboxType::class, [
                'required' => false,
                'label' => 'form.product.enabled.label',
            ])
            ->add('delete', SubmitType::class, [
                'label' => 'basics.delete',
            ]);

        $builder->add('allergens', ChoiceType::class, [
            'label' => 'form.product.allergens.label',
            'help' => 'form.product.allergens.help',
            'choices' => $this->createEnumAttributeChoices(Allergen::values(), 'allergens.%s'),
            'expanded' => true,
            'multiple' => true,
            'mapped' => false
        ]);

        $builder->add('restrictedDiets', ChoiceType::class, [
            'choices' => $this->createEnumAttributeChoices(RestrictedDiet::values(), 'restricted_diets.%s'),
            'label' => 'form.product.restricted_diets.label',
            'help' => 'form.product.restricted_diets.help',
            'expanded' => true,
            'multiple' => true,
            'mapped' => false
        ]);

        // While price & tax category are defined in ProductVariant,
        // we display the fields at the Product level
        // For now, all variants share the same values
        $builder
            ->add('price', MoneyType::class, [
                'mapped' => false,
                'divisor' => 100,
                'label' => 'form.product.price.label'
            ])
            ->add('taxCategory', TaxCategoryChoiceType::class, [
                'mapped' => false,
                'label' => 'form.product.taxCategory.label'
            ]);

        $builder->addEventListener(FormEvents::POST_SET_DATA, function (FormEvent $event) {

            $form = $event->getForm();
            $product = $event->getData();

            $form->add('options', CollectionType::class, [
                'entry_type' => ProductOptionWithPositionType::class,
                'entry_options' => [ 'label' => false ],
                'mapped' => false,
                'data' => $this->getSortedOptions($product),
            ]);

            if ($product->getRestaurant()->isDepositRefundEnabled()) {
                $form
                    ->add('reusablePackagingEnabled', CheckboxType::class, [
                        'required' => false,
                        'label' => 'form.product.reusable_packaging_enabled.label',
                    ])
                    ->add('reusablePackaging', EntityType::class, [
                        'label' => 'form.product.reusable_packaging.label',
                        'class' => ReusablePackaging::class,
                        'choices' => $product->getRestaurant()->getReusablePackagings(),
                        'choice_label' => 'name',
                    ])
                    ->add('reusablePackagingUnit', NumberType::class, [
                        'label' => 'form.product.reusable_packaging_unit.label',
                        'html5' => true,
                        'attr'  => array(
                            'min'  => 0,
                            'max'  => 3,
                            'step' => 0.5,
                        )
                    ]);
            }

            if (null !== $product->getId()) {

                $variant = $this->variantResolver->getVariant($product);

                // To keep things simple, all variants have the same price & tax category
                $form->get('price')->setData($variant->getPrice());
                $form->get('taxCategory')->setData($variant->getTaxCategory());
            }

            $this->postSetDataEnumAttribute($product, 'ALLERGENS', $form->get('allergens'));

            $this->postSetDataEnumAttribute($product, 'RESTRICTED_DIETS', $form->get('restrictedDiets'));
        });

        $builder->addEventListener(FormEvents::PRE_SUBMIT, function (FormEvent $event) {

            $form = $event->getForm();
            $data = $event->getData();

            // This is a delete button
            if (count($data) === 1 && isset($data['delete'])) {
                $form->remove('name');
                $form->remove('price');
                $form->remove('taxCategory');

                return;
            }

            $product = $form->getData();
            $name = $data['name'];

            // Skip new products
            if (null === $product->getId()) {
                return;
            }

            // Skip if name has not changed
            if ($name === $product->getName()) {
                return;
            }

            $form->add('confirm', SubmitType::class, [
                'label' => 'form.product.confirm.label',
            ]);

            // With PRE_SUBMIT we can't use isClicked()
            if (isset($data['confirm'])) {
                return;
            }

            // This will add an error to the "name" field
            $this->hasChangedName = true;
        });

        $builder->get('name')->addEventListener(FormEvents::PRE_SUBMIT, function (FormEvent $event) {
            if ($this->hasChangedName) {
                $event->getForm()->addError(new FormError(
                    $this->translator->trans('product.name.modified', [], 'validators'),
                    'product.name.modified',
                    []
                ));
            }
        });

        $builder->addEventListener(FormEvents::POST_SUBMIT, function (FormEvent $event) {

            $form = $event->getForm();
            $product = $event->getData();

            $opts = $form->get('options')->getData();
            foreach ($opts as $opt) {
                if ($opt['enabled']) {
                    $product->addOptionAt($opt['option'], $opt['position']);
                } else {
                    $product->removeOption($opt['option']);
                }
            }

            // This is a delete button
            if (!$form->has('price') && !$form->has('taxCategory')) {

                return;
            }

            $price = $form->get('price')->getData();
            $taxCategory = $form->get('taxCategory')->getData();

            if (null === $product->getId()) {

                $uuid = Uuid::uuid4()->toString();

                $product->setCode($uuid);
                $product->setSlug($uuid);

                $variant = $this->variantFactory->createForProduct($product);

                $variant->setName($product->getName());
                $variant->setCode(Uuid::uuid4()->toString());
                $variant->setPrice($price);
                $variant->setTaxCategory($taxCategory);

                $product->addVariant($variant);

            } else {
                foreach ($product->getVariants() as $variant) {
                    $variant->setName($product->getName());
                    $variant->setPrice($price);
                    $variant->setTaxCategory($taxCategory);
                }
            }

            $this->postSubmitEnumAttribute($product, 'ALLERGENS', $form->get('allergens')->getData());

            $this->postSubmitEnumAttribute($product, 'RESTRICTED_DIETS', $form->get('restrictedDiets')->getData());
        });
    }

    private function createEnumAttributeChoices(array $values, $format)
    {
        $choices = [];
        foreach ($values as $value) {
            $label = $this->translator->trans(sprintf($format, $value->getKey()));
            $choices[$value->getKey()] = $label;
        }

        asort($choices);

        return array_flip($choices);
    }

    private function postSetDataEnumAttribute(Product $product, $attributeCode, FormInterface $form)
    {
        $attributeValue = $product
            ->getAttributeByCodeAndLocale($attributeCode, $this->localeProvider->getDefaultLocaleCode());

        if (null !== $attributeValue) {
            $form->setData($attributeValue->getValue());
        }
    }

    private function postSubmitEnumAttribute(Product $product, $attributeCode, $data)
    {
        $attributeValue = $product
            ->getAttributeByCodeAndLocale($attributeCode, $this->localeProvider->getDefaultLocaleCode());

        if (null === $attributeValue) {
            $attribute =
                $this->productAttributeRepository->findOneBy(['code' => $attributeCode]);
            $attributeValue =
                $this->productAttributeValueFactory->createNew();

            $attributeValue->setAttribute($attribute);
            $attributeValue->setLocaleCode($this->localeProvider->getDefaultLocaleCode());
        }

        $attributeValue->setValue($data);

        $product->addAttribute($attributeValue);
    }

    private function getSortedOptions(Product $product)
    {
        $opts = [];
        foreach ($product->getRestaurant()->getProductOptions() as $opt) {
            $opts[] = [
                'product'  => $product,
                'option'   => $opt,
                'position' => $product->getPositionForOption($opt)
            ];
        }

        uasort($opts, function ($a, $b) {
            if ($a['position'] === $b['position']) return 0;
            if ($a['position'] === -1) return 1;
            if ($b['position'] === -1) return -1;
            return $a['position'] < $b['position'] ? -1 : 1;
        });

        return $opts;
    }

    public function configureOptions(OptionsResolver $resolver)
    {
        $resolver->setDefaults(array(
            'data_class' => Product::class,
        ));
    }
}
