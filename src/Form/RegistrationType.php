<?php

namespace AppBundle\Form;

use libphonenumber\PhoneNumberFormat;
use Misd\PhoneNumberBundle\Form\Type\PhoneNumberType;
use AppBundle\Form\Type\LegalType;
use Nucleos\ProfileBundle\Form\Type\RegistrationFormType;
use Symfony\Component\Form\AbstractTypeExtension;
use AppBundle\Service\SettingsManager;
use Symfony\Component\Form\Extension\Core\Type\CheckboxType;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\Form\Extension\Core\Type\HiddenType;
use Symfony\Component\Form\Extension\Core\Type\SubmitType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\Form\FormEvents;
use Symfony\Component\Form\FormEvent;
use Symfony\Component\OptionsResolver\OptionsResolver;
use AppBundle\Enum\Optin;
use AppBundle\Form\Type\TermsAndConditionsAndPrivacyPolicyType;

class RegistrationType extends AbstractTypeExtension
{
    private $settingsManager;
    private $splitTermsAndConditionsAndPrivacyPolicy;

    public function __construct(
        SettingsManager $settingsManager,
        string $country,
        bool $splitTermsAndConditionsAndPrivacyPolicy = false)
    {
        $this->settingsManager = $settingsManager;
        $this->splitTermsAndConditionsAndPrivacyPolicy = $splitTermsAndConditionsAndPrivacyPolicy;
        $this->country = strtoupper($country);
    }

    public function buildForm(FormBuilderInterface $builder, array $options)
    {
        if ('api' === $options['usage_context']) {

            $builder
                ->add('givenName', TextType::class)
                ->add('familyName', TextType::class)
                ->add('fullName', TextType::class, [
                    'property_path' => 'customer.fullName'
                ])
                ->add('telephone', PhoneNumberType::class, [
                    'required' => false,
                    'format' => PhoneNumberFormat::NATIONAL,
                    'default_region' => strtoupper($this->country)
                ]);

            return;
        }

        if ($this->splitTermsAndConditionsAndPrivacyPolicy) {
            $builder->add('termsAndConditionsAndPrivacyPolicy', TermsAndConditionsAndPrivacyPolicyType::class, [
                'label' => false,
            ]);
        } else {
            $builder->add('legal', LegalType::class, [
                'mapped' => false,
            ]);
        }

        // we need this data to iterate each optin form field in template
        $builder
        ->add('optins', HiddenType::class, [
            'mapped' => false,
            'data' => implode(",", Optin::toArray()),
        ]);

        // @see https://fr.sendinblue.com/blog/guide-opt-in/
        // @see https://mailchimp.com/fr/help/collect-consent-with-gdpr-forms/
        // @see https://www.mailerlite.com/blog/how-to-create-opt-in-forms-that-still-work-under-gdpr
        foreach(Optin::values() as $optin) {
            $builder->add($optin->getValue(), CheckboxType::class, [
                'label'    => $optin->label(),
                'label_translation_parameters' => $optin->labelParameters($this->settingsManager),
                'translation_domain' => 'messages',
                'required' => $optin->required(),
                'mapped'   => false,
            ]);
        }

        // Add help to "username" field
        $builder->addEventListener(FormEvents::POST_SET_DATA, function (FormEvent $event) {
            $form = $event->getForm();
            $child = $form->get('username');
            $config = $child->getConfig();
            $options = $config->getOptions();
            $options['help'] = 'form.registration.username.help';
            $form->add('username', get_class($config->getType()->getInnerType()), $options);
        });
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'usage_context' => 'web',
        ]);

        $resolver->setAllowedValues('usage_context', ['web', 'api']);
    }

    public static function getExtendedTypes(): iterable
    {
        return [
            RegistrationFormType::class
        ];
    }
}
