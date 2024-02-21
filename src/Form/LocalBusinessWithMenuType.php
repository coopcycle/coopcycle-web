<?php

namespace AppBundle\Form;

use AppBundle\Entity\BusinessRestaurantGroupRestaurantMenu;
use AppBundle\Entity\LocalBusiness;
use AppBundle\Entity\Sylius\Taxon;
use Symfony\Bridge\Doctrine\Form\Type\EntityType;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\Form\FormEvent;
use Symfony\Component\Form\FormEvents;
use Symfony\Component\Form\FormInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;

class LocalBusinessWithMenuType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options)
    {
        $builder
            ->add('restaurant', EntityType::class, [
                'class' => LocalBusiness::class,
                'choice_label' => 'name',
                'choice_value' => 'id',
            ])
            ->add('menu', EntityType::class, [
                'class' => Taxon::class,
                'choices' => [],
                'choice_label' => 'name',
                'choice_value' => 'id',
            ]);

        $modifier = function (FormInterface $form, LocalBusiness $restaurant = null) {
            $menus = null === $restaurant ? array() : $restaurant->getTaxons();

            $form->add('menu', EntityType::class, [
                'class' => Taxon::class,
                'choices' => $menus,
                'choice_label' => 'name',
                'choice_value' => 'id',
            ]);
        };

        $builder->addEventListener(FormEvents::POST_SET_DATA, function (FormEvent $event) use ($modifier) {
            $restaurantMenu = $event->getData();
            if ($restaurantMenu) {
                $modifier($event->getForm(), $restaurantMenu->getRestaurant());
            }
        });

        $builder->get('restaurant')->addEventListener(FormEvents::POST_SUBMIT, function (FormEvent $event) use ($modifier) {
            $restaurant = $event->getForm()->getData();
            $modifier($event->getForm()->getParent(), $restaurant);
        });

    }

    public function configureOptions(OptionsResolver $resolver)
    {
        $resolver->setDefaults(array(
            'data_class' => BusinessRestaurantGroupRestaurantMenu::class,
        ));
    }

}
