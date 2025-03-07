<?php

namespace AppBundle\Form;

use AppBundle\Entity\BusinessRestaurantGroup;
use AppBundle\Entity\BusinessRestaurantGroupPriceWithTax;
use AppBundle\Form\Type\PriceWithTaxType;
use Symfony\Bridge\Doctrine\Form\Type\EntityType;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\Form\FormEvents;
use Symfony\Component\Form\FormEvent;
use Symfony\Component\OptionsResolver\OptionsResolver;

class BusinessRestaurantGroupPriceType extends AbstractType
{

    public function buildForm(FormBuilderInterface $builder, array $options)
    {
        $builder->addEventListener(FormEvents::POST_SET_DATA, function (FormEvent $event) use($options) {

            $form = $event->getForm();

            $form
                ->add('businessRestaurantGroup', EntityType::class, [
                    'class' => BusinessRestaurantGroup::class,
                    'label' => 'business_restaurant_group.label',
                    'choices' => $options['choices'],
                    'choice_label' => 'name',
                    'choice_value' => 'id',
                    'placeholder' => 'form.product.business_restaurant_group.price_definition.placeholder',
                ])
                ->add('priceWithTax', PriceWithTaxType::class, [
                    'label' => false,
                    'for_local_business_group' =>true,
                ]);
        });

        $builder->addEventListener(FormEvents::POST_SET_DATA, function (FormEvent $event) {
            $form = $event->getForm();
            $businessRestaurantGroupPriceWithTax = $form->getData();

            if ($businessRestaurantGroupPriceWithTax && $businessRestaurantGroupPriceWithTax->getBusinessRestaurantGroup()) {
                $config = $form->get('priceWithTax')->getConfig();
                $priceForHubOptions = $config->getOptions();
                $priceForHubOptions['local_business_group'] = $businessRestaurantGroupPriceWithTax->getBusinessRestaurantGroup();

                $form->add('priceWithTax', get_class($config->getType()->getInnerType()), $priceForHubOptions);
            }
        });

        $builder->addEventListener(FormEvents::POST_SUBMIT, function (FormEvent $event) use ($options) {
            $form = $event->getForm();
            $businessRestaurantGroupPriceWithTax = $event->getData();

            $priceFormName = $options["taxIncl"] ? 'taxIncluded' : 'taxExcluded';
            $price = $form->get('priceWithTax')->get($priceFormName)->getData();

            $taxCategory = $form->get('priceWithTax')->get('taxCategory')->getData();

            $businessRestaurantGroupPriceWithTax->setPrice($price);
            $businessRestaurantGroupPriceWithTax->setTaxCategory($taxCategory);
        });
    }

    public function configureOptions(OptionsResolver $resolver)
    {
        $resolver->setDefaults(array(
            'data_class' => BusinessRestaurantGroupPriceWithTax::class,
            'owner' => null,
            'taxIncl' => true,
            'choices' => []
        ));
    }

}
