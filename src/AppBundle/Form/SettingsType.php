<?php

namespace AppBundle\Form;

use AppBundle\Service\SettingsManager;
use Doctrine\ORM\EntityRepository;
use libphonenumber\NumberParseException;
use libphonenumber\PhoneNumber;
use libphonenumber\PhoneNumberFormat;
use libphonenumber\PhoneNumberUtil;
use Misd\PhoneNumberBundle\Form\Type\PhoneNumberType;
use Sylius\Bundle\CurrencyBundle\Form\Type\CurrencyChoiceType;
use Sylius\Bundle\TaxationBundle\Form\Type\TaxCategoryChoiceType;
use Symfony\Bridge\Doctrine\Form\Type\EntityType;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\CallbackTransformer;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\Form\FormError;
use Symfony\Component\Form\FormEvents;
use Symfony\Component\Form\FormEvent;
use Symfony\Component\Form\FormInterface;
use Symfony\Component\Form\FormView;
use Symfony\Component\Form\Extension\Core\Type\CheckboxType;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\Form\Extension\Core\Type\CollectionType;
use Symfony\Component\Form\Extension\Core\Type\EmailType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\Extension\Core\Type\PasswordType;
use Symfony\Component\OptionsResolver\OptionsResolver;

class SettingsType extends AbstractType
{
    private $settingsManager;
    private $phoneNumberUtil;
    private $country;
    private $isDemo;

    public function __construct(
        SettingsManager $settingsManager,
        PhoneNumberUtil $phoneNumberUtil,
        string $country,
        bool $isDemo)
    {
        $this->settingsManager = $settingsManager;
        $this->phoneNumberUtil = $phoneNumberUtil;
        $this->country = $country;
        $this->isDemo = $isDemo;
    }

    private function createPlaceholder($value)
    {
        return implode('', array_pad([], strlen($value), '•'));
    }

    public function buildForm(FormBuilderInterface $builder, array $options)
    {
        $builder
            ->add('brand_name', TextType::class, [
                'label' => 'form.settings.brand_name.label',
                'disabled' => $this->isDemo
            ])
            ->add('administrator_email', EmailType::class, [
                'label' => 'form.settings.administrator_email.label',
                'help' => 'form.settings.administrator_email.help',
                'disabled' => $this->isDemo
            ])
            ->add('phone_number', PhoneNumberType::class, [
                'label' => 'form.settings.phone_number.label',
                'format' => PhoneNumberFormat::NATIONAL,
                'default_region' => strtoupper($this->country),
                'required' => false,
                'help' => 'form.settings.phone_number.help',
                'disabled' => $this->isDemo
            ])
            ->add('enable_restaurant_pledges', CheckboxType::class, [
                'label' => 'form.settings.enable_restaurant_pledges.label',
                'required' => false,
            ])
            ->add('stripe_test_publishable_key', PasswordType::class, [
                'required' => false,
                'label' => 'form.settings.stripe_publishable_key.label',
                'attr' => [
                    'autocomplete' => 'new-password'
                ]
            ])
            ->add('stripe_test_secret_key', PasswordType::class, [
                'required' => false,
                'label' => 'form.settings.stripe_secret_key.label',
                'attr' => [
                    'autocomplete' => 'new-password'
                ]
            ])
            ->add('stripe_test_connect_client_id', PasswordType::class, [
                'required' => false,
                'label' => 'form.settings.stripe_connect_client_id.label',
                'attr' => [
                    'autocomplete' => 'new-password'
                ]
            ])
            ->add('stripe_live_publishable_key', PasswordType::class, [
                'required' => false,
                'label' => 'form.settings.stripe_publishable_key.label',
                'attr' => [
                    'autocomplete' => 'new-password'
                ]
            ])
            ->add('stripe_live_secret_key', PasswordType::class, [
                'required' => false,
                'label' => 'form.settings.stripe_secret_key.label',
                'attr' => [
                    'autocomplete' => 'new-password'
                ]
            ])
            ->add('stripe_live_connect_client_id', PasswordType::class, [
                'required' => false,
                'label' => 'form.settings.stripe_connect_client_id.label',
                'attr' => [
                    'autocomplete' => 'new-password'
                ]
            ])
            ->add('google_api_key', TextType::class, [
                'label' => 'form.settings.google_api_key.label',
                'help' => 'form.settings.google_api_key.help',
                'help_html' => true,
                'disabled' => $this->isDemo
            ])
            ->add('latlng', TextType::class, [
                'label' => 'form.settings.latlng.label',
                'help' => 'form.settings.latlng.help',
                'help_html' => true

            ])
            ->add('default_tax_category', TaxCategoryChoiceType::class, [
                'label' => 'form.settings.default_tax_category.label',
                'help' => 'form.settings.default_tax_category.help'
            ])
            ->add('currency_code', CurrencyChoiceType::class, [
                'label' => 'form.settings.currency_code.label'
            ]);

        $builder->get('enable_restaurant_pledges')
            ->addModelTransformer(new CallbackTransformer(
                function ($originalValue) {
                    return filter_var($originalValue, FILTER_VALIDATE_BOOLEAN);
                },
                function ($submittedValue) {
                    return $submittedValue ? 'yes' : 'no';
                }
            ))
        ;

        $builder->addEventListener(FormEvents::PRE_SET_DATA, function (FormEvent $event) {

            $form = $event->getForm();
            $data = $event->getData();

            foreach ($data as $name => $value) {
                if ($this->settingsManager->isSecret($name)) {

                    $config = $form->get($name)->getConfig();
                    $options = $config->getOptions();

                    $options['empty_data'] = $value;
                    $options['required'] = false;
                    $options['attr'] = [
                        'placeholder' => $this->createPlaceholder($value),
                        'autocomplete' => 'new-password'
                    ];

                    $form->add($name, PasswordType::class, $options);
                }
            }

            // Make sure there is an empty choice
            if (!$data->default_tax_category) {

                $defaultTaxCategory = $form->get('default_tax_category');
                $options = $defaultTaxCategory->getConfig()->getOptions();

                $options['placeholder'] = '';
                $options['required'] = false;

                $form->add('default_tax_category', TaxCategoryChoiceType::class, $options);
            }

            // Make sure there is an empty choice
            if (!$data->currency_code) {

                $currencyCode = $form->get('currency_code');
                $options = $currencyCode->getConfig()->getOptions();

                $options['placeholder'] = '';
                $options['required'] = false;

                $form->add('currency_code', CurrencyChoiceType::class, $options);
            }

        });

        $builder->get('phone_number')->addEventListener(FormEvents::PRE_SET_DATA, function (FormEvent $event) {

            $form = $event->getForm();
            $data = $event->getData();

            try {
                $phoneNumber = $this->phoneNumberUtil->parse($data, strtoupper($this->country));
                $event->setData($phoneNumber);
            } catch (NumberParseException $e) {}
        });

        $builder->get('default_tax_category')->addEventListener(FormEvents::POST_SET_DATA, function (FormEvent $event) {

            $form = $event->getForm();
            $data = $event->getData();

            $options = $form->getConfig()->getOptions();
            foreach ($options['choices'] as $taxCategory) {
                if ($taxCategory->getCode() === $data) {
                    $form->setData($taxCategory);
                    break;
                }
            }
        });

        $builder->get('currency_code')->addEventListener(FormEvents::POST_SET_DATA, function (FormEvent $event) {

            $form = $event->getForm();
            $data = $event->getData();

            $options = $form->getConfig()->getOptions();
            foreach ($options['choices'] as $currency) {
                if ($currency->getCode() === $data) {
                    $form->setData($currency);
                    break;
                }
            }
        });

        $builder->addEventListener(FormEvents::SUBMIT, function (FormEvent $event) {
            $data = $event->getData();

            if (null !== $data->default_tax_category) {
                $data->default_tax_category = $data->default_tax_category->getCode();
            }
            if (null !== $data->currency_code) {
                $data->currency_code = $data->currency_code->getCode();
            }
            if (null !== $data->phone_number && $data->phone_number instanceof PhoneNumber) {
                $data->phone_number = $this->phoneNumberUtil->format($data->phone_number, PhoneNumberFormat::E164);
            }
            $event->setData($data);
        });
    }
}
