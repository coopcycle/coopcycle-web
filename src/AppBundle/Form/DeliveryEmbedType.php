<?php

namespace AppBundle\Form;

use AppBundle\Service\RoutingInterface;
use AppBundle\Service\SettingsManager;
use libphonenumber\PhoneNumberFormat;
use Misd\PhoneNumberBundle\Form\Type\PhoneNumberType;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Bridge\Doctrine\Form\Type\EntityType;
use Symfony\Component\Form\Extension\Core\Type\EmailType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormEvents;
use Symfony\Component\Form\FormEvent;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\Translation\TranslatorInterface;
use Misd\PhoneNumberBundle\Validator\Constraints\PhoneNumber as AssertPhoneNumber;
use Symfony\Component\Security\Core\Authorization\AuthorizationCheckerInterface;

class DeliveryEmbedType extends DeliveryType
{
    private $settingsManager;

    public function __construct(
        RoutingInterface $routing,
        TranslatorInterface $translator,
        AuthorizationCheckerInterface $authorizationChecker,
        string $country,
        string $locale,
        SettingsManager $settingsManager)
    {
        parent::__construct($routing, $translator, $authorizationChecker, $country, $locale);

        $this->settingsManager = $settingsManager;
    }

    public function buildForm(FormBuilderInterface $builder, array $options)
    {
        $options = array_merge($options, [
            'with_vehicle' => $this->settingsManager->getBoolean('embed.delivery.withVehicle'),
        ]);

        parent::buildForm($builder, $options);

        $withWeight = $this->settingsManager->getBoolean('embed.delivery.withWeight');
        if (!$withWeight) {
            $builder->remove('weight');
        }

        $builder
            ->add('name', TextType::class, [
                'mapped' => false,
                'label' => 'form.delivery_embed.name.label',
                'help' => 'form.delivery_embed.name.help'
            ])
            ->add('email', EmailType::class, [
                'mapped' => false,
                'label' => 'form.email',
                'translation_domain' => 'FOSUserBundle'
            ])
            ->add('telephone', PhoneNumberType::class, [
                'mapped' => false,
                'format' => PhoneNumberFormat::NATIONAL,
                'default_region' => strtoupper($this->country),
                'label' => 'form.delivery_embed.telephone.label',
                'constraints' => [
                    new AssertPhoneNumber()
                ],

            ])
            ->add('billingAddress', AddressType::class, [
                'mapped' => false,
                'extended' => true,
            ]);

        if ($options['with_payment']) {
            $builder->add('stripePayment', StripePaymentType::class, [
                'mapped' => false,
                'label' => false
            ]);
        }

        $builder->addEventListener(FormEvents::PRE_SUBMIT, function (FormEvent $event) use ($options) {

            $form = $event->getForm();
            $data = $event->getData();

            if (!$options['with_payment'] && isset($data['stripePayment'])) {
                unset($data['stripePayment']);
                $event->setData($data);
            }
        });

        $builder->addEventListener(FormEvents::POST_SET_DATA, function (FormEvent $event) {

            $form = $event->getForm();

            // This is here to avoid a BC break since AddressBookType was introduced
            // FIXME Use AddressBookType everywhere

            $form->get('pickup')->remove('address');
            $form->get('dropoff')->remove('address');

            $form->get('pickup')->add('address', AddressType::class);
            $form->get('dropoff')->add('address', AddressType::class);
        });
    }

    public function configureOptions(OptionsResolver $resolver)
    {
        parent::configureOptions($resolver);

        $resolver->setDefault('with_payment', false);

        // Disable CSRF protection to allow being used in iframes
        // @see https://github.com/coopcycle/coopcycle-web/issues/735
        $resolver->setDefault('csrf_protection', false);
    }
}
