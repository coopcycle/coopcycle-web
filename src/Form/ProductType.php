<?php

namespace AppBundle\Form;

use AppBundle\Entity\BusinessRestaurantGroup;
use AppBundle\Entity\BusinessRestaurantGroupPriceWithTax;
use AppBundle\Entity\BusinessRestaurantGroupRestaurantMenu;
use AppBundle\Entity\ReusablePackaging;
use AppBundle\Entity\LocalBusiness\CatalogInterface;
use AppBundle\Entity\Sylius\Product;
use AppBundle\Entity\Sylius\ProductOption;
use AppBundle\Enum\Allergen;
use AppBundle\Enum\RestrictedDiet;
use AppBundle\Form\Type\PriceWithTaxType;
use AppBundle\Sylius\Product\LazyProductVariantResolverInterface;
use AppBundle\Sylius\Product\ProductInterface;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\Query\Expr;
use Ramsey\Uuid\Uuid;
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
use Symfony\Component\Form\Extension\Core\Type\SubmitType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\Extension\Core\Type\TextareaType;
use Symfony\Component\Form\Extension\Core\Type\NumberType;
use Symfony\Component\OptionsResolver\Options;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Contracts\Translation\TranslatorInterface;

class ProductType extends AbstractType
{
    private $variantFactory;
    private $productAttributeRepository;
    private $productAttributeValueFactory;
    private $localeProvider;
    private $translator;
    private $entityManager;
    private $variantResolver;
    private $hasChangedName = false;

    public function __construct(
        ProductVariantFactoryInterface $variantFactory,
        RepositoryInterface $productAttributeRepository,
        FactoryInterface $productAttributeValueFactory,
        LocaleProviderInterface $localeProvider,
        TranslatorInterface $translator,
        EntityManagerInterface $entityManager,
        LazyProductVariantResolverInterface $variantResolver,
        bool $taxIncl = true,
        bool $businessAccountEnabled = false)
    {
        $this->variantFactory = $variantFactory;
        $this->productAttributeRepository = $productAttributeRepository;
        $this->productAttributeValueFactory = $productAttributeValueFactory;
        $this->localeProvider = $localeProvider;
        $this->translator = $translator;
        $this->entityManager = $entityManager;
        $this->variantResolver = $variantResolver;
        $this->taxIncl = $taxIncl;
        $this->businessAccountEnabled = $businessAccountEnabled;
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
            ->add('alcohol', CheckboxType::class, [
                'required' => false,
                'label' => 'form.product.alcohol.label',
                'help' => 'form.product.alcohol.help',
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
        $builder->add('priceWithTax', PriceWithTaxType::class, [
            'mapped' => false,
        ]);

        $businessRestaurantGroups = null;
        if ($this->businessAccountEnabled && null !== $options['owner']) {
            $qb = $this->entityManager->getRepository(BusinessRestaurantGroup::class)
                ->createQueryBuilder('b')
                ->select('b')
                ->innerJoin(BusinessRestaurantGroupRestaurantMenu::class, 'g', Expr\Join::WITH, 'g.businessRestaurantGroup = b.id AND g.restaurant = :restaurant')
                ->setParameter('restaurant', $options['owner'])
                ->orderBy('b.name');

            $businessRestaurantGroups = $qb->getQuery()->getResult();

            if (count($businessRestaurantGroups) > 0) {
                $builder->add('businessRestaurantGroupPrices', CollectionType::class, [
                    'entry_type' => BusinessRestaurantGroupPriceType::class,
                    'entry_options' => [
                        'taxIncl' => $this->taxIncl,
                        'owner' => $options['owner'],
                        'choices' => $businessRestaurantGroups
                    ],
                    'label' => 'form.product.business_restaurant_group.price_definition.label',
                    'mapped' => false,
                    'allow_add' => true,
                    'allow_delete' => true,
                    'prototype_name' => '__price__'
                ]);
            }

        }

        $builder->addEventListener(FormEvents::POST_SET_DATA, function (FormEvent $event) use ($options, $businessRestaurantGroups) {

            $form = $event->getForm();
            $product = $event->getData();

            $form->add('options', CollectionType::class, [
                'entry_type' => ProductOptionWithPositionType::class,
                'entry_options' => [ 'label' => false ],
                'mapped' => false,
                'data' => $this->getSortedOptions($product, $options),
            ]);

            if (null !== $product->getId()) {

                if ($options['with_reusable_packaging']) {

                    $entryOptions = [
                        'label' => false,
                        'reusable_packaging_choice_loader' => $options['reusable_packaging_choice_loader'],
                    ];

                    if ($product->getRestaurant()->isLoopeatEnabled()) {
                        $entryOptions['units_step'] = 1;
                    }

                    $form
                        ->add('reusablePackagingEnabled', CheckboxType::class, [
                            'required' => false,
                            'label' => 'form.product.reusable_packaging_enabled.label',
                        ])
                        ->add('reusablePackagings', CollectionType::class, [
                            'label' => false,
                            'entry_type' => ReusablePackagingType::class,
                            'entry_options' => $entryOptions,
                            'allow_add' => true,
                            'by_reference' => false,
                        ])
                        ;
                }

                if ($form->has('businessRestaurantGroupPrices')) {
                    $data = [];

                    foreach($businessRestaurantGroups as $businessRestaurantGroup) {
                        $variant = $this->variantResolver->getVariantForBusinessRestaurantGroup($product, $businessRestaurantGroup);
                        if ($variant) {
                            $data[] = new BusinessRestaurantGroupPriceWithTax(
                                            $businessRestaurantGroup,
                                            $variant->getPrice(),
                                            $variant->getTaxCategory()
                                        );;
                        }
                    }

                    $form->get('businessRestaurantGroupPrices')->setData($data);
                }
            }

            $this->postSetDataEnumAttribute($product, 'ALLERGENS', $form->get('allergens'));

            $this->postSetDataEnumAttribute($product, 'RESTRICTED_DIETS', $form->get('restrictedDiets'));
        });

        $builder->addEventListener(FormEvents::PRE_SUBMIT, function (FormEvent $event) {

            $form = $event->getForm();
            $data = $event->getData();

            // This is a delete button (used in list of products)
            if (count($data) === 1 && isset($data['delete'])) {
                foreach (array_keys($form->all()) as $key) {
                    if ($key !== 'delete') {
                        $form->remove($key);
                    }
                }
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

        $builder->addEventListener(FormEvents::POST_SUBMIT, function (FormEvent $event) use ($options, $businessRestaurantGroups) {

            $form = $event->getForm();
            $product = $event->getData();

            if ($form->has('options')) {
                $opts = $form->get('options')->getData();
                foreach ($opts as $opt) {
                    if ($opt['enabled']) {
                        $product->addOptionAt($opt['option'], $opt['position']);
                    } else {
                        $product->removeOption($opt['option']);
                    }
                }
            }

            // This is a delete button (used in list of products)
            if (count($form) === 1 && $form->has('delete')) {

                return;
            }

            $priceFormName = $this->taxIncl ? 'taxIncluded' : 'taxExcluded';
            $price = $form->get('priceWithTax')->get($priceFormName)->getData();

            $taxCategory = $form->get('priceWithTax')->get('taxCategory')->getData();

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

            if ($form->has('businessRestaurantGroupPrices')) {
                $businessRestaurantGroupPrices = $form->get('businessRestaurantGroupPrices')->getData();

                $submitedBusinessRestaurantGroups = new ArrayCollection();
                foreach ($businessRestaurantGroupPrices as $businessRestaurantGroupPrice) {
                    $submitedBusinessRestaurantGroups->add($businessRestaurantGroupPrice->getBusinessRestaurantGroup()->getId());

                    if (null === $product->getId()) {
                        $this->createAndAddVariantForProduct($product, $businessRestaurantGroupPrice);
                    } else {
                        $variant = $this->variantResolver->getVariantForBusinessRestaurantGroup($product, $businessRestaurantGroupPrice->getBusinessRestaurantGroup());

                        if ($variant === null) {
                            $this->createAndAddVariantForProduct($product, $businessRestaurantGroupPrice);
                        } else {
                            $variant->setName($product->getName());
                            $variant->setPrice($businessRestaurantGroupPrice->getPrice());
                            $variant->setTaxCategory($businessRestaurantGroupPrice->getTaxCategory());
                        }
                    }
                }

                foreach ($businessRestaurantGroups as $businessRestaurantGroup) {
                    if (!in_array($businessRestaurantGroup->getId(), $submitedBusinessRestaurantGroups->toArray())) {
                        $variantToRemove = $this->variantResolver->getVariantForBusinessRestaurantGroup($product, $businessRestaurantGroup);
                        if (null !== $variantToRemove) {
                            $product->removeVariant($variantToRemove);
                        }
                    }
                }
            }

            $this->postSubmitEnumAttribute($product, 'ALLERGENS', $form->get('allergens')->getData());

            $this->postSubmitEnumAttribute($product, 'RESTRICTED_DIETS', $form->get('restrictedDiets')->getData());

            if (null !== $options['owner']) {
                $options['owner']->addProduct($product);
            }
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

    private function getSortedOptions(ProductInterface $product, array $options)
    {
        if (is_callable($options['options_loader'])) {

            return call_user_func_array($options['options_loader'], [ $product ]);
        }

        return [];
    }

    private function createAndAddVariantForProduct(
        Product $product,
        BusinessRestaurantGroupPriceWithTax $businessRestaurantGroupPrice
    )
    {
        $variant = $this->variantFactory->createForProduct($product);

        $variant->setName($product->getName());
        $variant->setCode(Uuid::uuid4()->toString());
        $variant->setPrice($businessRestaurantGroupPrice->getPrice());
        $variant->setTaxCategory($businessRestaurantGroupPrice->getTaxCategory());
        $variant->setBusinessRestaurantGroup($businessRestaurantGroupPrice->getBusinessRestaurantGroup());

        $product->addVariant($variant);
    }

    public function configureOptions(OptionsResolver $resolver)
    {
        $resolver->setDefaults(array(
            'data_class' => Product::class,
            'owner' => null,
            'with_reusable_packaging' => false,
            'reusable_packaging_choice_loader' => null,
            'options_loader' => null,
        ));
        $resolver->setAllowedTypes('owner', CatalogInterface::class);
        $resolver->setAllowedTypes('with_reusable_packaging', 'bool');
        $resolver->setAllowedTypes('options_loader', ['null', 'callable']);
    }
}
