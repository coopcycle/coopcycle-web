<?php

namespace AppBundle\Form;

use AppBundle\Entity\Sylius\ProductOption;
use AppBundle\Entity\Sylius\ProductOptions;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\CallbackTransformer;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\Form\Extension\Core\Type\CheckboxType;
use Symfony\Component\Form\Extension\Core\Type\HiddenType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormEvent;
use Symfony\Component\Form\FormEvents;

class ProductOptionWithPositionType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options)
    {
        $builder
            ->add('option', HiddenType::class, [
                'attr' => [
                    'data-name' => 'option'
                ]
            ])
            ->add('position', HiddenType::class, [
                'attr' => [
                    'data-name' => 'position'
                ]
            ]);

        $builder->addEventListener(FormEvents::POST_SET_DATA, function (FormEvent $event) {

            $form = $event->getForm();
            $data = $event->getData();

            $form
                ->add('enabled', CheckboxType::class, [
                    'required' => false,
                    'label' => $data['name'] ?? '',
                    'data' => $data['enabled'] ?? false,
                ]);
        });
    }
}
