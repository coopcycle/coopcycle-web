<?php

namespace AppBundle\Form\Checkout;

use AppBundle\Entity\Sylius\Customer;
use AppBundle\Form\AddressType;
use AppBundle\Form\Type\LegalType;
use AppBundle\Form\Type\PhoneNumberType;
use AppBundle\Utils\PriceFormatter;
use AppBundle\Validator\Constraints\UserWithSameEmailNotExists as AssertUserWithSameEmailNotExists;
use FOS\UserBundle\Util\CanonicalizerInterface;
use Sylius\Component\Resource\Factory\FactoryInterface;
use Sylius\Component\Resource\Repository\RepositoryInterface;
use Symfony\Component\Form\Extension\Core\Type\CheckboxType;
use Symfony\Component\Form\Extension\Core\Type\EmailType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\Form\FormError;
use Symfony\Component\Form\FormEvents;
use Symfony\Component\Form\FormEvent;
use Symfony\Component\Form\FormInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\Translation\TranslatorInterface;
use Symfony\Component\Validator\Constraints as Assert;
use Misd\PhoneNumberBundle\Validator\Constraints\PhoneNumber as AssertPhoneNumber;

class CheckoutCustomerType extends AbstractType
{
    private $translator;
    private $customerFactory;
    private $canonicalizer;
    private $customerRepository;

    public function __construct(
        TranslatorInterface $translator,
        FactoryInterface $customerFactory,
        CanonicalizerInterface $canonicalizer,
        RepositoryInterface $customerRepository)
    {
        $this->translator = $translator;
        $this->customerFactory = $customerFactory;
        $this->canonicalizer = $canonicalizer;
        $this->customerRepository = $customerRepository;
    }

    public function buildForm(FormBuilderInterface $builder, array $options)
    {
        $builder->addEventListener(FormEvents::POST_SET_DATA, function (FormEvent $event) {

            $form = $event->getForm();
            $parentForm = $form->getParent();

            $order = $parentForm->getData();
            $customer = $order->getCustomer();

            if (null === $customer || !$customer->hasUser()) {
                $form->add('email', EmailType::class, [
                    'label' => 'form.email',
                    'translation_domain' => 'FOSUserBundle',
                    'constraints' => [
                        new Assert\NotBlank(),
                        new Assert\Email([
                            'mode' => Assert\Email::VALIDATION_MODE_STRICT,
                        ]),
                        new AssertUserWithSameEmailNotExists(),
                    ],
                    'help' => 'form.email.help',
                    'data' => $customer !== null ? $customer->getEmailCanonical() : '',
                ]);
            }

            if (null === $customer || !$customer->hasUser() || empty($customer->getFullName())) {
                $form->add('fullName', TextType::class, [
                    'label' => 'profile.fullName',
                    'constraints' => [
                        new Assert\NotBlank()
                    ],
                    'data' => $customer !== null ? $customer->getFullName() : '',
                ]);
            }

            if (null === $customer || !$customer->hasUser() || empty($customer->getPhoneNumber())) {
                $form->add('phoneNumber', PhoneNumberType::class, [
                    'label' => 'form.checkout_address.telephone.label',
                    'constraints' => [
                        new Assert\NotBlank(),
                        new AssertPhoneNumber(),
                    ],
                    'help' => 'form.checkout_address.telephone.help',
                    'data' => $customer !== null ? $customer->getPhoneNumber() : '',
                ]);
            }

            if (null === $customer || !$customer->hasUser()) {
                $form->add('legal', LegalType::class);
            }
        });

        $builder->addEventListener(FormEvents::SUBMIT, function (FormEvent $event) {

            $form = $event->getForm();

            if ($form->has('email') && $form->get('email')->isValid()) {

                $email = $form->get('email')->getData();
                $emailCanonical = $this->canonicalizer->canonicalize($email);

                $customer = $this->customerRepository
                    ->findOneBy([
                        'emailCanonical' => $emailCanonical,
                    ]);

                if (null !== $customer) {
                    $event->setData($customer);
                } else {
                    $event->getData()->setEmailCanonical($emailCanonical);
                }
            }
        });
    }

    public function configureOptions(OptionsResolver $resolver)
    {
        $resolver->setDefaults(array(
            'data_class' => Customer::class,
            // 'validation_groups' => ['sylius_customer_guest'],
        ));
    }
}
