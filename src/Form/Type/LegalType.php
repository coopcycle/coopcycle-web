<?php

namespace AppBundle\Form\Type;

use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\CheckboxType;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;

class LegalType extends AbstractType
{
    private $urlGenerator;

    public function __construct(UrlGeneratorInterface $urlGenerator)
    {
        $this->urlGenerator = $urlGenerator;
    }

    public function configureOptions(OptionsResolver $resolver)
    {
        parent::configureOptions($resolver);

        $resolver->setDefaults([
            'mapped' => false,
            'required' => true,
            'label' => 'form.registration.legal.label',
            'help' => 'form.registration.legal.help',
            'help_translation_parameters' => [
                '%terms_url%' => $this->urlGenerator->generate('terms', [], UrlGeneratorInterface::ABSOLUTE_URL),
                '%privacy_url%' => $this->urlGenerator->generate('privacy', [], UrlGeneratorInterface::ABSOLUTE_URL),
            ],
            'help_html' => true,
        ]);
    }

    public function getParent()
    {
        return CheckboxType::class;
    }
}
