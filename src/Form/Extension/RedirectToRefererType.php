<?php

namespace AppBundle\Form\Extension;

use AppBundle\Form\Listener\RedirectToRefererSubscriber;
use Symfony\Component\Form\AbstractTypeExtension;
use Symfony\Component\Form\Extension\Core\Type\FormType;
use Symfony\Component\Form\Extension\Core\Type\HiddenType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\OptionsResolver\OptionsResolver;

/**
 * To be used in a form to redirect to the referer after submission.
 *
 * Usage example:
 *
 * 1. Enable the feature in your form type:
 *
 * public function configureOptions(OptionsResolver $resolver)
 * {
 * $resolver->setDefaults(array(
 * ...
 * 'redirect_to_enabled' => true,
 * ...
 * ));
 * }
 *
 * 2. Redirect to the referer after form submission:
 *
 * $redirectUri = $form->has('__redirect_to') ? $form->get('__redirect_to')->getData() : null;
 * return $redirectUri ? $this->redirect($redirectUri) : [fallback redirect url];
 *
 */
class RedirectToRefererType extends AbstractTypeExtension
{
    public function __construct(
        private readonly RequestStack $requestStack
    )
    {}

    public function buildForm(FormBuilderInterface $builder, array $options)
    {
        // If the feature is not enabled, we quit
        if (!$options['redirect_to_enabled']) {
            return;
        }

        // add a hidden field
        $builder->add('__redirect_to', HiddenType::class, [
            'mapped' => false,
        ]);

        // dynamically set the redirect uri
        $builder->get('__redirect_to')->addEventSubscriber(new RedirectToRefererSubscriber($this->requestStack));
    }

    public function configureOptions(OptionsResolver $resolver)
    {
        // disable by default
        $resolver->setDefault('redirect_to_enabled', false);
        $resolver->setAllowedTypes('redirect_to_enabled', 'bool');
    }

    public static function getExtendedTypes(): iterable
    {
        return [
            FormType::class
        ];
    }
}
