<?php

namespace AppBundle\Form;

use AppBundle\Entity\Delivery\PricingRuleSet;
use AppBundle\Entity\PackageSet;
use AppBundle\Entity\Store;
use AppBundle\Entity\TimeSlot;
use Doctrine\ORM\EntityRepository;
use Symfony\Bridge\Doctrine\Form\Type\EntityType;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\CheckboxType;
use Symfony\Component\Form\Extension\Core\Type\SubmitType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\Form\FormEvents;
use Symfony\Component\Form\FormEvent;
use Symfony\Component\Form\FormError;
use Symfony\Component\Form\FormInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\Validator\Validation;
use Symfony\Component\Validator\Constraints;
use Vich\UploaderBundle\Form\Type\VichImageType;

class StoreType extends LocalBusinessType
{
    public function buildForm(FormBuilderInterface $builder, array $options)
    {
        parent::buildForm($builder, $options);

        $builder->remove('openingHours');

        if ($this->authorizationChecker->isGranted('ROLE_ADMIN')) {
            $builder
                ->add('pricingRuleSet', EntityType::class, array(
                    'label' => 'form.store_type.pricing_rule_set.label',
                    'class' => PricingRuleSet::class,
                    'choice_label' => 'name',
                    'query_builder' => function (EntityRepository $er) {
                        return $er->createQueryBuilder('prs')->orderBy('prs.name', 'ASC');
                    }
                ))
                ->add('packageSet', EntityType::class, array(
                    'label' => 'form.store_type.package_set.label',
                    'class' => PackageSet::class,
                    'choice_label' => 'name',
                    'query_builder' => function (EntityRepository $er) {
                        return $er->createQueryBuilder('ps')->orderBy('ps.name', 'ASC');
                    },
                    'required' => false,
                ))
                ->add('prefillPickupAddress', CheckboxType::class, [
                    'label' => 'form.store_type.prefill_pickup_address.label',
                    'required' => false,
                ])
                ->add('createOrders', CheckboxType::class, [
                    'label' => 'form.store_type.create_orders.label',
                    'required' => false,
                ])
                ->add('timeSlot', EntityType::class, [
                    'label' => 'form.store_type.time_slot.label',
                    'class' => TimeSlot::class,
                    'choice_label' => 'name',
                    'required' => false,
                ]);
        }

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

                // Remove default address form
                $form->remove('address');
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
            } else {
                if (null === $store->getId()) {
                    $defaultAddress = $store->getAddress();
                    if (!$store->getAddresses()->contains($defaultAddress)) {
                        $store->addAddress($defaultAddress);
                    }
                }
            }
        });
    }

    public function configureOptions(OptionsResolver $resolver)
    {
        parent::configureOptions($resolver);

        $resolver->setDefaults(array(
            'data_class' => Store::class,
        ));
    }
}
