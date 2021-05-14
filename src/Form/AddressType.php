<?php

namespace AppBundle\Form;

use AppBundle\Entity\Address;
use AppBundle\Entity\Base\GeoCoordinates;
use libphonenumber\PhoneNumberFormat;
use Misd\PhoneNumberBundle\Form\Type\PhoneNumberType;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\TextareaType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\Form\FormEvents;
use Symfony\Component\Form\FormEvent;
use Symfony\Component\Form\FormError;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\Extension\Core\Type\HiddenType;
use Symfony\Component\Form\Extension\Core\Type\SearchType;
use Symfony\Component\Form\Extension\Core\Type\SubmitType;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Contracts\Translation\TranslatorInterface;
use Symfony\Component\Validator\Validation;
use Symfony\Component\Validator\Constraints;
use Misd\PhoneNumberBundle\Validator\Constraints\PhoneNumber as AssertPhoneNumber;

class AddressType extends AbstractType
{
    private $translator;
    private $country;

    public function __construct(TranslatorInterface $translator, string $country)
    {
        $this->translator = $translator;
        $this->country = $country;
    }

    public function buildForm(FormBuilderInterface $builder, array $options)
    {
        $streetAddressOptions = [
            'label' => $options['street_address_label'],
            'attr' => [
                // autocomplete="off" doesn't work in Chrome
                // https://developer.mozilla.org/en-US/docs/Web/Security/Securing_your_site/Turning_off_form_autocompletion
                // https://bugs.chromium.org/p/chromium/issues/detail?id=468153#c164
                'autocomplete' => uniqid()
            ],
        ];

        if (isset($options['placeholder']) && !empty($options['placeholder'])) {
            $streetAddressOptions['attr']['placeholder'] = $options['placeholder'];
        }

        if ($options['with_widget']) {
            $streetAddressOptions['attr']['data-widget'] = 'address-input';
        }

        $builder
            ->add('streetAddress', SearchType::class, $streetAddressOptions)
            ->add('postalCode', HiddenType::class, [
                'required' => false,
                'label' => 'form.address.postalCode.label'
            ])
            ->add('addressLocality', HiddenType::class, [
                'required' => false,
                'label' => 'form.address.addressLocality.label'
            ])

            ->add('latitude', HiddenType::class, [
                'mapped' => false,
            ])
            ->add('longitude', HiddenType::class, [
                'mapped' => false,
            ])
            ;

        if (true === $options['extended']) {
            $builder
                ->add('company', TextType::class, [
                    'label' => 'form.address.company.label',
                    'required' => false,
                ]);
        }

        if (true === $options['with_name']) {
            $builder
                ->add('name', TextType::class, [
                    'required' => false,
                    'label' => 'form.address.name.label',
                    'attr' => ['placeholder' => 'form.address.name.placeholder']
                ]);
        }

        if (true === $options['with_telephone']) {
            $builder
                ->add('telephone', PhoneNumberType::class, [
                    'format' => PhoneNumberFormat::NATIONAL,
                    'default_region' => strtoupper($this->country),
                    'required' => false,
                ]);
        }

        if (true === $options['with_contact_name']) {
            $builder
                ->add('contactName', TextType::class, [
                    'label' => 'form.task.recipient.label',
                    'help' => 'form.task.recipient.help',
                    'required' => false,
                ]);
        }

        if (true === $options['with_description']) {
            $builder
                ->add('description', TextareaType::class, [
                    'required' => false,
                    'label' => 'form.address.description.label',
                    'attr' => ['rows' => '3', 'placeholder' => 'form.address.description.placeholder']
                ]);
        }

        $constraints = [
            new Constraints\NotBlank(),
            new Constraints\Type(['type' => 'numeric']),
        ];

        // Make sure latitude/longitude is valid
        $latLngListener = function (FormEvent $event) use ($constraints) {
            $form = $event->getForm();
            $address = $event->getData();

            $streetAddress = $form->get('streetAddress')->getData();
            if (!empty($streetAddress)) {
                $latitude = $form->get('latitude')->getData();
                $longitude = $form->get('longitude')->getData();

                $validator = Validation::createValidator();

                $latitudeViolations = $validator->validate($latitude, $constraints);
                $longitudeViolations = $validator->validate($longitude, $constraints);

                if (count($latitudeViolations) > 0 || count($longitudeViolations) > 0) {

                    $message = 'form.address.streetAddress.error.noLatLng';
                    $error = new FormError(
                        $this->translator->trans($message),
                        $message,
                        $messageParameters = [],
                        $messagePluralization = null,
                        $cause = count($latitudeViolations) > 0 ? $latitudeViolations->get(0) : $longitudeViolations->get(0)
                    );

                    $form->get('streetAddress')->addError($error);
                } else {
                    $address->setGeo(new GeoCoordinates($latitude, $longitude));
                }
            }
        };

        $builder->addEventListener(FormEvents::SUBMIT, $latLngListener);
        $builder->addEventListener(FormEvents::POST_SET_DATA, function (FormEvent $event) {
            $form = $event->getForm();
            $address = $event->getData();
            if (null !== $address) {
                if ($geo = $address->getGeo()) {
                    $form->get('latitude')->setData($geo->getLatitude());
                    $form->get('longitude')->setData($geo->getLongitude());
                }
            }
        });

    }

    public function configureOptions(OptionsResolver $resolver)
    {
        $resolver->setDefaults(array(
            'data_class' => Address::class,
            'extended' => false,
            'with_telephone' => false,
            'with_name' => false,
            'placeholder' => null,
            'street_address_label' => 'form.address.streetAddress.label',
            'with_widget' => false,
            'with_description' => true,
            'with_contact_name' => false,
        ));
    }
}
