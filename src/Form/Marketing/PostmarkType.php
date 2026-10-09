<?php

namespace AppBundle\Form\Marketing;

use AppBundle\Form\PaymentGateway\BaseType;
use AppBundle\Service\Marketing\PostmarkClient;
use Symfony\Component\Form\Extension\Core\Type\PasswordType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;

/**
 * Per-tenant Postmark credentials for marketing sends.
 *
 * Extends the payment gateways' BaseType only for its plumbing: it is what
 * maps an unmapped sub-form onto the settings object.
 */
class PostmarkType extends BaseType
{
    public function buildForm(FormBuilderInterface $builder, array $options)
    {
        parent::buildForm($builder, $options);

        $builder
            ->add('postmark_server_token', PasswordType::class, [
                'required' => false,
                'label' => 'form.settings.postmark_server_token.label',
                'help' => 'form.settings.postmark_server_token.help',
                'attr' => [
                    'autocomplete' => 'new-password',
                ],
            ])
            ->add('postmark_broadcast_stream', TextType::class, [
                'required' => false,
                'label' => 'form.settings.postmark_broadcast_stream.label',
                'help' => 'form.settings.postmark_broadcast_stream.help',
                'attr' => [
                    'placeholder' => PostmarkClient::DEFAULT_BROADCAST_STREAM,
                ],
            ])
            ->add('postmark_webhook_secret', PasswordType::class, [
                'required' => false,
                'label' => 'form.settings.postmark_webhook_secret.label',
                'help' => 'form.settings.postmark_webhook_secret.help',
                'attr' => [
                    'autocomplete' => 'new-password',
                ],
            ]);
    }
}
