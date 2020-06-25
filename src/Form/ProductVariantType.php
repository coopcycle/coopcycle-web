<?php

namespace AppBundle\Form;

use AppBundle\Entity\Sylius\ProductVariant;
use AppBundle\Form\Type\MoneyType;
use Ramsey\Uuid\Uuid;
use Sylius\Bundle\ProductBundle\Form\EventSubscriber\BuildProductVariantFormSubscriber;
use Sylius\Bundle\ResourceBundle\Form\EventSubscriber\AddCodeFormSubscriber;
use Sylius\Bundle\TaxationBundle\Form\Type\TaxCategoryChoiceType;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\Form\FormEvents;
use Symfony\Component\Form\FormEvent;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\OptionsResolver\Options;
use Symfony\Component\OptionsResolver\OptionsResolver;

class ProductVariantType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options)
    {
        $builder
            ->add('name', TextType::class)
            ->add('price', MoneyType::class)
            ->add('taxCategory', TaxCategoryChoiceType::class);

        $builder->addEventListener(FormEvents::PRE_SUBMIT, function (FormEvent $event) {
            $productVariant = $event->getForm()->getData();
            if (null === $productVariant->getCode()) {
                $uuid = Uuid::uuid4()->toString();
                $productVariant->setCode($uuid);
            }
        });
    }

    public function configureOptions(OptionsResolver $resolver)
    {
        $resolver->setDefaults(array(
            'data_class' => ProductVariant::class,
        ));
    }
}
