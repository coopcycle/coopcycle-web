<?php

namespace AppBundle\Form;

use AppBundle\Entity\Delivery\PricingRuleSet;
use Doctrine\ORM\EntityRepository;
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

class QuoteEmbedType extends QuoteType
{
    public function buildForm(FormBuilderInterface $builder, array $options)
    {
        parent::buildForm($builder, $options);

        $builder
        ->add('pricingRuleSet', EntityType::class, array(
            'required' => true,
            'placeholder' => 'form.store_type.pricing_rule_set.placeholder',
            'label' => 'form.store_type.pricing_rule_set.label',
            'class' => PricingRuleSet::class,
            'choice_label' => 'name',
            'mapped' => false,
            'query_builder' => function (EntityRepository $er) {
                return $er->createQueryBuilder('prs')->orderBy('prs.name', 'ASC');
            }
        ));

        $builder->addEventListener(FormEvents::POST_SUBMIT, function (FormEvent $event) {

            $form = $event->getForm();

            $delivery = $form->getData();

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
