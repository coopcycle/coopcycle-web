<?php

namespace AppBundle\Form;

use ApiPlatform\Core\Api\IriConverterInterface;
use AppBundle\Entity\Address;
use Doctrine\ORM\PersistentCollection;
use libphonenumber\NumberParseException;
use libphonenumber\PhoneNumberUtil;
use Symfony\Bridge\Doctrine\Form\Type\EntityType;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\CheckboxType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\Form\FormEvents;
use Symfony\Component\Form\FormEvent;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\Serializer\SerializerInterface;

/**
 * Custom form type to choose between an existing address, or a new address.
 *
 * It renders a dropdown + a text field to enter a new address.
 *
 * <select name="existingAddress">
 *   <option></option>
 * </select>
 * <input name="newAddress">
 *
 * @see https://symfony.com/doc/current/form/create_custom_field_type.html
 */
class AddressBookType extends AbstractType
{
    private $iriConverter;
    private $serializer;

    public function __construct(
        IriConverterInterface $iriConverter,
        SerializerInterface $serializer,
        PhoneNumberUtil $phoneNumberUtil,
        string $country)
    {
        $this->iriConverter = $iriConverter;
        $this->serializer = $serializer;
        $this->phoneNumberUtil = $phoneNumberUtil;
        $this->country = $country;
    }

    public function buildForm(FormBuilderInterface $builder, array $options)
    {
        $newAddressOptions = [
            'label' => false,
            'required' => false,
            'mapped' => false,
            'with_name' => false,
            'with_telephone' => false,
            'with_contact_name' => false,
        ];

        if (isset($options['new_address_placeholder']) && !empty($options['new_address_placeholder'])) {
            $newAddressOptions['placeholder'] = $options['new_address_placeholder'];
        }

        $builder
            ->add('existingAddress', EntityType::class, [
                'class' => Address::class,
                'choices' => $options['with_addresses'],
                'choice_label' => 'streetAddress',
                'choice_value' => function (Address $address = null) {
                    return $address && null !== $address->getId() ? $this->iriConverter->getIriFromItem($address) : '';
                },
                'choice_attr' => function(Address $choice, $key, $value) {

                    if ($choice->getId() !== null) {

                        return [
                            'data-address' => $this->serializer->serialize($choice, 'jsonld', [
                                'groups' => ['delivery_create', 'task_create']
                            ])
                        ];
                    }

                    return [];
                },
                'label' => false,
                'required' => false,
                'mapped' => false,
            ])
            ->add('newAddress', AddressType::class, $newAddressOptions)
            ->add('isNewAddress', CheckboxType::class, [
                'label' => false,
                'required' => false,
                'mapped' => false,
            ]);

        if ($options['with_address_props']) {
            $builder
                // We require telephone & contactName,
                // but the name is still optional
                ->add('name', TextType::class, [
                    'required' => false,
                    'mapped' => false,
                ])
                ->add('telephone', TextType::class, [
                    'required' => true,
                    'mapped' => false,
                ])
                ->add('contactName', TextType::class, [
                    'required' => true,
                    'mapped' => false,
                ]);
        }

        if ($options['with_remember_address']) {
            $builder->add('rememberAddress', CheckboxType::class, [
                'label' => 'form.adress_book.remember_address',
                'required' => false,
                'mapped' => false,
            ]);
        }

        $builder->addEventListener(FormEvents::POST_SET_DATA, function (FormEvent $event) use ($options) {

            $form = $event->getForm();
            $address = $event->getData();

            if (null !== $address) {

                $addresses = $options['with_addresses'];
                if (!is_array($addresses)) {
                    if (is_callable([ $addresses, 'toArray' ])) {
                        $addresses = $addresses->toArray();
                    }
                }

                // If the address is not part of the address book, add it
                // FIXME It's not 100% satisfying, because the address does not belong to the address book
                if (!in_array($address, $addresses, true)) {
                    $config = $form->get('existingAddress')->getConfig();
                    $options = $config->getOptions();
                    $options['choices'] = array_merge($addresses, [ $address ]);

                    $form->add('existingAddress', get_class($config->getType()->getInnerType()), $options);
                }

                $form->get('existingAddress')->setData($address);
            } else {
                $form->get('isNewAddress')->setData(true);
            }
        });

        $builder->addEventListener(FormEvents::SUBMIT, function (FormEvent $event) {

            $form = $event->getForm();
            $address = $event->getData();

            $existingAddress = $form->get('existingAddress')->getData();
            $newAddress = $form->get('newAddress')->getData();
            $isNewAddress = $form->get('isNewAddress')->getData();

            $addressToModify = $isNewAddress ? $newAddress : $existingAddress;

            if ($form->has('name')) {
                $name = $form->get('name')->getData();
                $addressToModify->setName($name);
            }

            if ($form->has('contactName')) {
                $contactName = $form->get('contactName')->getData();
                $addressToModify->setContactName($contactName);
            }

            if ($form->has('telephone')) {
                try {
                    $telephone = $form->get('telephone')->getData();
                    $addressToModify->setTelephone(
                        $this->phoneNumberUtil->parse($telephone, strtoupper($this->country))
                    );
                } catch (NumberParseException $e) {}
            }

            $event->setData($addressToModify);
        });
    }

    public function configureOptions(OptionsResolver $resolver)
    {
        $resolver->setDefaults(array(
            'data_class' => Address::class,
            'with_addresses' => [],
            'new_address_placeholder' => null,
            'with_remember_address' => false,
            'with_address_props' => false,
        ));
    }

    public function getBlockPrefix()
    {
        return 'coopcycle_address_book';
    }
}
