<?php

namespace AppBundle\Form;

use AppBundle\Service\SettingsManager;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\CheckboxType;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\Form\FormEvents;
use Symfony\Component\Form\FormEvent;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;

class RegistrationType extends AbstractType
{
    private $settingsManager;
    private $urlGenerator;
    private $isDemo;

    public function __construct(SettingsManager $settingsManager, UrlGeneratorInterface $urlGenerator, bool $isDemo = false)
    {
        $this->settingsManager = $settingsManager;
        $this->urlGenerator = $urlGenerator;
        $this->isDemo = $isDemo;
    }

    public function buildForm(FormBuilderInterface $builder, array $options)
    {
        $builder
            ->add('givenName', TextType::class, array('label' => 'profile.givenName'))
            ->add('familyName', TextType::class, array('label' => 'profile.familyName'))
            // Phone number will be asked during checkout
            // @see AppBundle\Form\Checkout\CheckoutAddressType
            ->add('legal', CheckboxType::class, array(
                'mapped' => false,
                'required' => true,
                'label' => 'form.registration.legal.label',
                'help' => 'form.registration.legal.help',
                'help_translation_parameters' => [
                    '%terms_url%' => $this->urlGenerator->generate('terms', [], UrlGeneratorInterface::ABSOLUTE_URL),
                    '%privacy_url%' => $this->urlGenerator->generate('privacy', [], UrlGeneratorInterface::ABSOLUTE_URL),
                ],
                'help_html' => true,
            ))
            ;

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

    public function getParent()
    {
        return 'FOS\UserBundle\Form\Type\RegistrationFormType';
    }
}
