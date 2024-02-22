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
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\Extension\Core\Type\UrlType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\Form\FormEvent;
use Symfony\Component\Form\FormEvents;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use Symfony\Component\Security\Core\Authorization\AuthorizationCheckerInterface;
use Symfony\Component\Validator\Constraints as Assert;

class BusinessAccountType extends AbstractType
{
    private $authorizationChecker;
    private $objectManager;
    private $urlGenerator;

    public function __construct(
        AuthorizationCheckerInterface $authorizationChecker,
        EntityManagerInterface $objectManager,
        UrlGeneratorInterface $urlGenerator)
    {
        $this->authorizationChecker = $authorizationChecker;
        $this->objectManager = $objectManager;
        $this->urlGenerator = $urlGenerator;
    }

    public function buildForm(FormBuilderInterface $builder, array $options)
    {
        $builder
            ->add('name', TextType::class, ['label' => 'registration.step.company.name']);

        $businessRestaurantGroup = $this->objectManager->getRepository(BusinessRestaurantGroup::class)->findAll();

        if ($this->authorizationChecker->isGranted('ROLE_ADMIN')) {
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
        } else {
            $builder
                ->add('address', AddressType::class, [
                    'with_widget' => true,
                    'with_description' => false,
                    'label' => 'registration.company.address',
                ])
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

        $builder->addEventListener(FormEvents::POST_SET_DATA, function (FormEvent $event) {
            $businessAccount = $event->getData();
            $form = $event->getForm();

            if (null !== $businessAccount->getId()) {
                $businessAccountInvitation = $this->objectManager->getRepository(BusinessAccountInvitation::class)
                    ->findOneBy([
                        'businessAccount' => $businessAccount,
                    ]);
                if (null !== $businessAccountInvitation) {
                    if ($this->authorizationChecker->isGranted('ROLE_ADMIN')) {
                        $form->add('managerEmail', EmailType::class, [
                            'label' => 'form.business_account.manager.email.label',
                            'help' => 'form.business_account.manager.email_sent.help',
                            'disabled' => true,
                            'required'=> false,
                            'mapped'=> false,
                            'data' => $businessAccountInvitation->getInvitation()->getEmail(),
                        ]);
                    }

                    if ($this->authorizationChecker->isGranted('ROLE_BUSINESS_ACCOUNT')) {
                        $invitationLink = $this->urlGenerator->generate('invitation_define_password', [
                            'code' => $businessAccountInvitation->getInvitation()->getCode()
                        ], UrlGeneratorInterface::ABSOLUTE_URL);
                        $form->add('invitationLink', UrlType::class, [
                            'mapped' => false,
                            'label' => 'registration.step.invitation.copy.link',
                            'data' => $invitationLink
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
                        'required'=> true,
                        'mapped'=> false,
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
        ));
    }

    public function getBlockPrefix() {
		return 'company';
	}
}
