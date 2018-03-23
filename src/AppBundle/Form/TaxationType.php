<?php

namespace AppBundle\Form;

use Doctrine\ORM\EntityRepository;
use Sylius\Bundle\TaxationBundle\Form\Type\TaxCategoryType;
use Sylius\Bundle\TaxationBundle\Form\Type\TaxRateType;
use Sylius\Component\Taxation\Model\TaxCategory;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\Form\Extension\Core\Type\DateType;
use Symfony\Component\Form\Extension\Core\Type\SubmitType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\Extension\Core\Type\TextareaType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\Form\FormEvents;
use Symfony\Component\Form\FormEvent;
use Symfony\Component\Form\FormError;
use Symfony\Component\Form\FormInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;

class TaxationType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options)
    {
        $builder
            ->add('taxCategory', TaxCategoryType::class, [
                'mapped' => false,
                'label' => false
            ])
            ->add('taxRate', TaxRateType::class, [
                'mapped' => false,
                'label' => false
            ]);

        $builder->get('taxCategory')->addEventListener(FormEvents::PRE_SET_DATA, function (FormEvent $event) {

            $form = $event->getForm();
            $parentForm = $event->getForm()->getParent();
            $taxCategory = $parentForm->getData();

            $form->remove('code');

            $event->setData($taxCategory);
        });

        $builder->get('taxRate')->addEventListener(FormEvents::PRE_SET_DATA, function (FormEvent $event) {

            $form = $event->getForm();
            $parentForm = $event->getForm()->getParent();
            $taxCategory = $parentForm->getData();
            $taxRate = $taxCategory->getRates()->get(0);

            $form->remove('category');
            $form->remove('code');
            $form->remove('name');
            $form->remove('includedInPrice');

            $event->setData($taxRate);
        });
    }

    public function configureOptions(OptionsResolver $resolver)
    {
        $resolver->setDefaults(array(
            'data_class' => TaxCategory::class,
        ));
    }
}
