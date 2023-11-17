<?php

namespace AppBundle\Form;

use AppBundle\Entity\BusinessAccount;
use AppBundle\Entity\BusinessAccountInvitation;
use AppBundle\Entity\Hub;
use AppBundle\Entity\LocalBusiness;
use AppBundle\Validator\Constraints\UserWithSameEmailNotExists as AssertUserWithSameEmailNotExists;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bridge\Doctrine\Form\Type\EntityType;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\CheckboxType;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\Form\Extension\Core\Type\CollectionType;
use Symfony\Component\Form\Extension\Core\Type\EmailType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\Form\FormEvent;
use Symfony\Component\Form\FormEvents;
use Symfony\Component\Form\FormInterface;
use Symfony\Component\Form\FormView;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\Security\Core\Authorization\AuthorizationCheckerInterface;
use Symfony\Component\Validator\Constraints as Assert;

class BusinessAccountType extends AbstractType
{
    private $authorizationChecker;
    private $objectManager;

    public function __construct(
        AuthorizationCheckerInterface $authorizationChecker,
        EntityManagerInterface $objectManager)
    {
        $this->authorizationChecker = $authorizationChecker;
        $this->objectManager = $objectManager;
    }

    public function buildForm(FormBuilderInterface $builder, array $options)
    {
        $builder
            ->add('name', TextType::class, ['label' => 'registration.step.company.name']);

        $hubs = $this->objectManager->getRepository(Hub::class)->findAll();

        if ($this->authorizationChecker->isGranted('ROLE_ADMIN')) {
            $builder
                ->add('hub', ChoiceType::class, [
                    'label' => 'form.business_account.hub.label',
                    'placeholder' => 'form.business_account.hub.placeholder',
                    'choices' => $hubs,
                    'choice_label' => 'name',
                    'expanded' => false,
                    'multiple' => false,
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
                if ($this->authorizationChecker->isGranted('ROLE_ADMIN')) {
                    $businessAccountInvitation = $this->objectManager->getRepository(BusinessAccountInvitation::class)
                        ->findOneBy([
                            'businessAccount' => $businessAccount,
                        ]);
                    if (null !== $businessAccountInvitation) {
                        $form->add('managerEmail', EmailType::class, [
                            'label' => 'form.business_account.manager.email.label',
                            'help' => 'form.business_account.manager.email_sent.help',
                            'disabled' => true,
                            'required'=> false,
                            'mapped'=> false,
                            'data' => $businessAccountInvitation->getInvitation()->getEmail(),
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
                            new AssertUserWithSameEmailNotExists(),
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
