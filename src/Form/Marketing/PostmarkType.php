<?php

namespace AppBundle\Form\Marketing;

use AppBundle\Service\Marketing\PostmarkClient;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\PasswordType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\Validator\Constraints as Assert;

/**
 * Per-tenant Postmark credentials for marketing sends.
 *
 * Lives with the campaigns rather than in the global settings: it is only
 * meaningful to someone setting up campaigns, and an admin configuring one
 * shouldn't have to leave the page to do it.
 *
 * Plain scalars through Craue/SettingsManager, same shape as the loyalty
 * settings form.
 */
class PostmarkType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('postmark_server_token', PasswordType::class, [
                'required' => false,
                'label' => 'marketing.postmark.field.server_token',
                'help' => 'marketing.postmark.field.server_token.help',
                'attr' => [
                    'autocomplete' => 'new-password',
                ],
            ])
            ->add('postmark_broadcast_stream', TextType::class, [
                'required' => false,
                'label' => 'marketing.postmark.field.broadcast_stream',
                'help' => 'marketing.postmark.field.broadcast_stream.help',
                'attr' => [
                    'placeholder' => PostmarkClient::DEFAULT_BROADCAST_STREAM,
                ],
            ])
            ->add('postmark_sender_name', TextType::class, [
                'required' => false,
                'label' => 'marketing.postmark.field.sender_name',
                'help' => 'marketing.postmark.field.sender_name.help',
            ])
            ->add('postmark_sender_email', TextType::class, [
                'required' => false,
                'label' => 'marketing.postmark.field.sender_email',
                'help' => 'marketing.postmark.field.sender_email.help',
                'constraints' => [new Assert\Email()],
            ])
            ->add('postmark_webhook_secret', PasswordType::class, [
                'required' => false,
                'label' => 'marketing.postmark.field.webhook_secret',
                'help' => 'marketing.postmark.field.webhook_secret.help',
                'attr' => [
                    'autocomplete' => 'new-password',
                ],
            ]);
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => null,
        ]);
    }
}
