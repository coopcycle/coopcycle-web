<?php

namespace AppBundle\Form;

use AppBundle\Form\Type\LegalType;
use Nucleos\ProfileBundle\Form\Type\RegistrationFormType;
use Symfony\Component\Form\AbstractTypeExtension;
use AppBundle\Service\SettingsManager;
use Symfony\Component\Form\Extension\Core\Type\CheckboxType;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\Form\Extension\Core\Type\SubmitType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\Form\FormEvents;
use Symfony\Component\Form\FormEvent;

class RegistrationType extends AbstractTypeExtension
{
    private $settingsManager;
    private $isDemo;

    public function __construct(SettingsManager $settingsManager, bool $isDemo = false)
    {
        $this->settingsManager = $settingsManager;
        $this->isDemo = $isDemo;
    }

    public function buildForm(FormBuilderInterface $builder, array $options)
    {
        $builder->add('legal', LegalType::class);

        if ($this->isDemo) {
            $builder->add('accountType', ChoiceType::class, [
                'mapped' => false,
                'required' => true,
                'choices'  => [
                    'roles.ROLE_USER' => 'CUSTOMER',
                    'roles.ROLE_COURIER' => 'COURIER',
                    'roles.ROLE_RESTAURANT' => 'RESTAURANT',
                    'roles.ROLE_STORE' => 'STORE',
                ],
                'label' => 'profile.accountType'
            ]);
        }

        // @see https://fr.sendinblue.com/blog/guide-opt-in/
        // @see https://mailchimp.com/fr/help/collect-consent-with-gdpr-forms/
        // @see https://www.mailerlite.com/blog/how-to-create-opt-in-forms-that-still-work-under-gdpr
        $builder->add('newsletterOptin', CheckboxType::class, [
            'label'    => 'form.registration.newsletter_optin.label',
            'label_translation_parameters' => [
                '%brand_name%' => $this->settingsManager->get('brand_name'),
            ],
            'required' => false,
            'mapped'   => false,
        ]);
        $builder->add('marketingOptin', CheckboxType::class, [
            'label'    => 'form.registration.marketing_optin.label',
            'required' => false,
            'mapped'   => false,
        ]);

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

    public static function getExtendedTypes(): iterable
    {
        return [
            RegistrationFormType::class
        ];
    }
}
