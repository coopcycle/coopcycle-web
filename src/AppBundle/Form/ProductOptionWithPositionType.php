<?php

namespace AppBundle\Form;

use AppBundle\Entity\Sylius\ProductOption;
use AppBundle\Entity\Sylius\ProductOptions;
use Doctrine\Persistence\ManagerRegistry;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\CallbackTransformer;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\Form\Extension\Core\Type\CheckboxType;
use Symfony\Component\Form\Extension\Core\Type\HiddenType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormEvent;
use Symfony\Component\Form\FormEvents;
use Symfony\Component\OptionsResolver\OptionsResolver;

class ProductOptionWithPositionType extends AbstractType
{
    public function __construct(ManagerRegistry $doctrine)
    {
        $this->doctrine = $doctrine;
    }

    public function buildForm(FormBuilderInterface $builder, array $options)
    {
        $builder
            ->add('option', HiddenType::class)
            ->add('position', HiddenType::class, [
                'attr' => [
                    'data-name' => 'position'
                ]
            ]);

        $builder
            ->get('option')
            ->addModelTransformer(new CallbackTransformer(
                function ($entity) {
                    if (is_callable([ $entity, 'getId' ])) {
                        return $entity->getId();
                    }
                },
                function ($id) {
                    if (!$id) {
                        return null;
                    }

                    return $this->doctrine->getRepository(ProductOption::class)->find($id);
                }
            ));

        $builder->addEventListener(FormEvents::POST_SET_DATA, function (FormEvent $event) {

            $form = $event->getForm();
            $data = $event->getData();

            $form
                ->add('enabled', CheckboxType::class, [
                    'required' => false,
                    'label' => $data['option']->getName(),
                    'data' => $data['product']->hasOption($data['option']),
                ]);
        });
    }
}
