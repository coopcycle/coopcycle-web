<?php

namespace AppBundle\Form;

use AppBundle\Entity\PackageSet;
use AppBundle\Entity\TimeSlot;
use AppBundle\Service\RoutingInterface;
use AppBundle\Service\SettingsManager;
use Doctrine\ORM\EntityManagerInterface;
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
use Symfony\Contracts\Translation\TranslatorInterface;
use Misd\PhoneNumberBundle\Validator\Constraints\PhoneNumber as AssertPhoneNumber;
use Symfony\Component\Security\Core\Authorization\AuthorizationCheckerInterface;

class DeliveryEmbedType extends DeliveryType
{
    public function buildForm(FormBuilderInterface $builder, array $options)
    {
        parent::buildForm($builder, $options);

        $builder
            ->add('name', TextType::class, [
                'mapped' => false,
                'label' => 'form.delivery_embed.name.label',
                'help' => 'form.delivery_embed.name.help'
            ])
            ->add('email', EmailType::class, [
                'mapped' => false,
                'label' => 'form.email',
                'translation_domain' => 'NucleosProfileBundle'
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
                'with_widget' => true,
                'with_description' => false,
                'label' => false,
                'required' => false,
            ]);

        $builder->addEventListener(FormEvents::POST_SUBMIT, function (FormEvent $event) {

            $form = $event->getForm();

            $delivery = $form->getData();
            $contactName = $form->get('name')->getData();

            foreach ($delivery->getTasks() as $task) {
                if (null !== $task->getAddress()) {
                    $task->getAddress()->setContactName($contactName);
                }
            }
        });
    }

    public function configureOptions(OptionsResolver $resolver)
    {
        parent::configureOptions($resolver);

        $resolver->setDefault('with_tags', false);

        // Disable CSRF protection to allow being used in iframes
        // @see https://github.com/coopcycle/coopcycle-web/issues/735
        $resolver->setDefault('csrf_protection', false);
    }
}
