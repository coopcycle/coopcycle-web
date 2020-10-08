<?php

namespace AppBundle\Form\Checkout;

use AppBundle\Entity\Sylius\Customer;
use AppBundle\Form\AddressType;
use AppBundle\Utils\PriceFormatter;
use AppBundle\Validator\Constraints\UserWithSameEmailNotExists as AssertUserWithSameEmailNotExists;
use FOS\UserBundle\Util\CanonicalizerInterface;
use libphonenumber\PhoneNumberFormat;
use libphonenumber\PhoneNumberUtil;
use Misd\PhoneNumberBundle\Form\Type\PhoneNumberType;
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
    private $country;

    public function __construct(
        TranslatorInterface $translator,
        FactoryInterface $customerFactory,
        CanonicalizerInterface $canonicalizer,
        RepositoryInterface $customerRepository,
        string $country)
    {
        $this->translator = $translator;
        $this->customerFactory = $customerFactory;
        $this->canonicalizer = $canonicalizer;
        $this->customerRepository = $customerRepository;
        $this->country = strtoupper($country);
    }

    public function buildForm(FormBuilderInterface $builder, array $options)
    {
        $builder->addEventListener(FormEvents::POST_SET_DATA, function (FormEvent $event) {

            $form = $event->getForm();
            $customer = $event->getData();

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
                ]);
            }

            if (null === $customer || !$customer->hasUser()) {
                $form->add('firstName', TextType::class, [
                    'label' => 'profile.givenName',
                    'constraints' => [
                        new Assert\NotBlank()
                    ],
                ]);
            }

            if (null === $customer || !$customer->hasUser()) {
                $form->add('lastName', TextType::class, [
                    'label' => 'profile.familyName',
                    'constraints' => [
                        new Assert\NotBlank()
                    ],
                ]);
            }

            if (null === $customer || !$customer->hasUser() || empty($customer->getPhoneNumber())) {
                $form->add('phoneNumber', PhoneNumberType::class, [
                    'format' => PhoneNumberFormat::NATIONAL,
                    'default_region' => $this->country,
                    'label' => 'form.checkout_address.telephone.label',
                    'constraints' => [
                        new Assert\NotBlank(),
                        new AssertPhoneNumber(),
                    ],
                    // We use mapped = false, because phoneNumber is a string
                    // If we don't do this, PhoneNumberToStringTransformer trigger an error
                    // "Expected a \libphonenumber\PhoneNumber"
                    'mapped' => false,
                    'help' => 'form.checkout_address.telephone.help',
                ]);
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

        $builder->addEventListener(FormEvents::POST_SUBMIT, function (FormEvent $event) {

            $form = $event->getForm();
            $customer = $form->getData();

            if ($form->has('phoneNumber') && $form->get('phoneNumber')->isValid()) {

                $phoneNumber = PhoneNumberUtil::getInstance()->format(
                    $form->get('phoneNumber')->getData(),
                    PhoneNumberFormat::E164
                );

                if ($phoneNumber !== $customer->getPhoneNumber()) {
                    $customer->setPhoneNumber($phoneNumber);
                }
            }
        });
    }

    public function configureOptions(OptionsResolver $resolver)
    {
        $resolver->setDefaults(array(
            'data_class' => Customer::class,
        ));
    }
}
