<?php

namespace AppBundle\Form\Type;

use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\CheckboxType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;

class TermsAndConditionsAndPrivacyPolicyType extends AbstractType
{
    private $urlGenerator;

    public function __construct(UrlGeneratorInterface $urlGenerator)
    {
        $this->urlGenerator = $urlGenerator;
    }

    public function buildForm(FormBuilderInterface $builder, array $options)
    {
        $builder->add('termsAndConditions', CheckboxType::class ,[
            'required' => true,
            'label' => 'form.registration.terms.and.conditions.label',
            'help' => 'form.registration.terms.and.conditions.help',
            'translation_domain' => 'messages',
            'help_translation_parameters' => [
                '%terms_url%' => $this->urlGenerator->generate('terms', [], UrlGeneratorInterface::ABSOLUTE_URL),
                '%privacy_url%' => $this->urlGenerator->generate('privacy', [], UrlGeneratorInterface::ABSOLUTE_URL),
            ],
            'help_html' => true,
        ]);

        $builder->add('privacyPolicy', CheckboxType::class, [
            'required' => true,
            'label' => 'form.registration.privacy.policy.label',
            'help' => 'form.registration.privacy.policy.help',
            'translation_domain' => 'messages',
            'help_translation_parameters' => [
                '%privacy_url%' => $this->urlGenerator->generate('privacy', [], UrlGeneratorInterface::ABSOLUTE_URL),
            ],
            'help_html' => true,
        ]);
    }

}
