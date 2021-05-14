<?php

namespace AppBundle\Form;

use AppBundle\Entity\Store;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\SubmitType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\Form\FormEvents;
use Symfony\Component\Form\FormEvent;
use Symfony\Component\Form\FormError;
use Symfony\Component\Form\FormInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;

class StoreAddressesType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options)
    {
        $builder->addEventListener(FormEvents::POST_SET_DATA, function (FormEvent $event) {

            $form = $event->getForm();
            $store = $event->getData();

            if (null !== $store && null !== $store->getId()) {
                foreach ($store->getAddresses() as $address) {
                    if ($address !== $store->getAddress()) {
                        $form->add(sprintf('setAsDefault_%s', $address->getId()), SubmitType::class, [
                            'label' => 'form.store_type.setAsDefault.label',
                            'attr' => [ 'data-address' => $address->getId() ]
                        ]);
                    }
                }
            }
        });

        $builder->addEventListener(FormEvents::POST_SUBMIT, function (FormEvent $event) {

            $form = $event->getForm();
            $store = $event->getData();

            if ($form->getClickedButton()) {
                $options = $form->getClickedButton()->getConfig()->getOptions();
                $addressId = $options['attr']['data-address'];
                foreach ($store->getAddresses() as $storeAddress) {
                    if ($storeAddress->getId() === $addressId) {
                        $store->setAddress($storeAddress);
                        break;
                    }
                }
            }
        });
    }

    public function configureOptions(OptionsResolver $resolver)
    {
        $resolver->setDefaults(array(
            'data_class' => Store::class,
        ));
    }
}
