<?php

namespace AppBundle\Form;

use AppBundle\Entity\BusinessAccount;
use AppBundle\Entity\BusinessAccountInvitation;
use AppBundle\Entity\BusinessRestaurantGroup;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\CheckboxType;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\Form\Extension\Core\Type\EmailType;
use Symfony\Component\Form\Extension\Core\Type\HiddenType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\Form\FormEvent;
use Symfony\Component\Form\FormEvents;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\Security\Core\Authorization\AuthorizationCheckerInterface;
use Symfony\Component\Validator\Constraints as Assert;

class BusinessAccountType extends AbstractType
{
    public function __construct(
        private readonly AuthorizationCheckerInterface $authorizationChecker,
        private readonly EntityManagerInterface $objectManager)
    {
    }

    public function buildForm(FormBuilderInterface $builder, array $options)
    {
        $builder
            ->add('name', TextType::class, [
                'label' => 'registration.step.company.name'
            ])
            ->add('legalName', TextType::class, [
                'label' => 'registration.step.company.legal_name',
                'required' => !$this->authorizationChecker->isGranted('ROLE_ADMIN'),
                'empty_data' => ''])
            ->add('vatNumber', TextType::class, [
                'label' => 'registration.step.company.vat_number',
                'required' => !$this->authorizationChecker->isGranted('ROLE_ADMIN'),
                'empty_data' => ''])
            ->add('address', AddressType::class, [
                'with_widget' => true,
                'with_description' => false,
                'label' => 'registration.company.address',
            ]);

        $businessRestaurantGroup = $this->objectManager->getRepository(BusinessRestaurantGroup::class)->findAll();

        if ($this->authorizationChecker->isGranted('ROLE_ADMIN') && !$options['business_account_registration']) {
            $builder
                ->add('businessRestaurantGroup', ChoiceType::class, [
                    'label' => 'form.business_account.businessRestaurantGroup.label',
                    'placeholder' => 'form.business_account.businessRestaurantGroup.placeholder',
                    'choices' => $businessRestaurantGroup,
                    'choice_label' => 'name',
                    'expanded' => false,
                    'multiple' => false,
                    'required' => false,
                ]);
        }

        if ($this->authorizationChecker->isGranted('ROLE_BUSINESS_ACCOUNT') || $options['business_account_registration']) {
            $builder
                ->add('differentAddressForBilling', CheckboxType::class, [
                    'label' => 'registration.company.address.different.for.billing',
                    'required' => false,
                ])
                ->add('billingAddress', AddressType::class, [
                    'with_widget' => true,
                    'with_description' => false,
                    'label' => 'registration.company.billing.address',
                ]);
        }

        $builder->addEventListener(FormEvents::POST_SET_DATA, function (FormEvent $event) use ($options) {
            $businessAccount = $event->getData();
            $form = $event->getForm();

            if (null !== $businessAccount->getId()) {
                $businessAccountInvitation = $this->objectManager->getRepository(BusinessAccountInvitation::class)
                    ->findOneBy([
                        'businessAccount' => $businessAccount,
                    ]);
                if (null !== $businessAccountInvitation) {
                    if ($this->authorizationChecker->isGranted('ROLE_ADMIN') && !$options['business_account_registration']) {
                        $form
                            ->add('managerEmail', EmailType::class, [
                                'label' => 'form.business_account.manager.email.label',
                                'help' => 'form.business_account.manager.email_sent.help',
                                'help_html' => true,
                                'disabled' => true,
                                'required' => false,
                                'mapped' => false,
                                'data' => $businessAccountInvitation->getInvitation()->getEmail(),
                            ])
                            ->add('invitationId', HiddenType::class, [
                                'mapped' => false,
                                'data' => $businessAccountInvitation->getId()
                            ]);
                    }
                }
            } else {
                if ($this->authorizationChecker->isGranted('ROLE_ADMIN')) {
                    $form->add('managerEmail', EmailType::class, [
                        'constraints' => [
                            new Assert\NotBlank(),
                            new Assert\Email([
                                'mode' => Assert\Email::VALIDATION_MODE_STRICT,
                            ]),
                        ],
                        'label' => 'form.business_account.manager.email.label',
                        'help' => 'form.business_account.manager.email.help',
                        'required' => true,
                        'mapped' => false,
                    ]);
                }
            }
        });

        $builder->addEventListener(FormEvents::SUBMIT, function (FormEvent $event) {
            $form = $event->getForm();
            $businessAccount = $form->getData();

            if (!$businessAccount->getDifferentAddressForBilling()) {
                $businessAccount->setBillingAddress(null);
            }
        });
    }

    public function configureOptions(OptionsResolver $resolver)
    {
        $resolver->setDefaults(array(
            'data_class' => BusinessAccount::class,
            // this flag is useful for cases when a logged in admin user is trying to register a new business account
            // https://github.com/coopcycle/coopcycle-web/issues/4155
            'business_account_registration' => false,
        ));
    }

    public function getBlockPrefix()
    {
        return 'company';
    }
}
