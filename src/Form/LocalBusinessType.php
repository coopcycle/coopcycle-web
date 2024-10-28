<?php

namespace AppBundle\Form;

use AppBundle\Service\FormFieldUtils;
use AppBundle\Validator\Constraints\Siret as AssertSiret;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use libphonenumber\PhoneNumberFormat;
use Misd\PhoneNumberBundle\Form\Type\PhoneNumberType;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\Form\FormEvents;
use Symfony\Component\Form\FormEvent;
use Symfony\Component\Form\Extension\Core\Type\CheckboxType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\Extension\Core\Type\HiddenType;
use Symfony\Component\Form\Extension\Core\Type\UrlType;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\Security\Core\Authentication\Token\Storage\TokenStorageInterface;
use Symfony\Component\Security\Core\Authorization\AuthorizationCheckerInterface;
use Symfony\Component\Serializer\SerializerInterface;
use Symfony\Component\Validator\Constraints as Assert;
use Vich\UploaderBundle\Form\Type\VichImageType;
use AppBundle\Payment\GatewayResolver;
use AppBundle\Security\UserManager;

abstract class LocalBusinessType extends AbstractType
{
    protected $authorizationChecker;
    protected $tokenStorage;
    protected $entityManager;
    protected $serializer;
    protected $urlGenerator;
    protected $country;
    protected $debug;
    protected $cashOnDeliveryOptinEnabled;
    protected bool $transportersEnabled;
    protected array $transportersConfig;
    protected bool $billingEnabled;

    public function __construct(
        AuthorizationCheckerInterface $authorizationChecker,
        TokenStorageInterface $tokenStorage,
        EntityManagerInterface $entityManager,
        SerializerInterface $serializer,
        GatewayResolver $gatewayResolver,
        UrlGeneratorInterface $urlGenerator,
        protected UserManager $userManager,
        protected FormFieldUtils $formFieldUtils,
        string $country,
        bool $debug = false,
        bool $cashOnDeliveryOptinEnabled = false,
        array $transportersConfig = [],
        bool $billingEnabled = false,
    )
    {
        $this->authorizationChecker = $authorizationChecker;
        $this->tokenStorage = $tokenStorage;
        $this->entityManager = $entityManager;
        $this->serializer = $serializer;
        $this->urlGenerator = $urlGenerator;
        $this->country = $country;
        $this->debug = $debug;
        $this->cashOnDeliveryOptinEnabled = $cashOnDeliveryOptinEnabled;
        $this->gatewayResolver = $gatewayResolver;
        $this->transportersEnabled = !empty($transportersConfig);
        $this->transportersConfig = $transportersConfig;
        $this->billingEnabled = $billingEnabled;
    }

    public function buildForm(FormBuilderInterface $builder, array $options)
    {
        $builder
            ->add('enabled', CheckboxType::class, [
                'label' => 'localBusiness.form.enabled',
                'required' => false
            ])
            ->add('name', TextType::class, ['label' => 'localBusiness.form.name'])
            ->add('legalName', TextType::class, ['required' => false, 'label' => 'localBusiness.form.legalName',])
            ->add('website', UrlType::class, ['required' => false, 'label' => 'localBusiness.form.website',])
            ->add('address', AddressType::class, [
                'with_widget' => true,
                'with_description' => false,
                'label' => false,
            ])
            ->add('telephone', PhoneNumbertype::class, [
                'default_region' => strtoupper($this->country),
                'format' => PhoneNumberFormat::NATIONAL,
                'required' => false,
                'label' => 'localBusiness.form.telephone',
            ]);

        if ($this->billingEnabled) {
            $builder->add('billingMethod', ChoiceType::class, [
                'label' => 'form.billing_method.label',
                'help' => 'form.billing_method.help',
                'choices' => [
                    'form.billing_method.unit' => 'unit',
                    'form.billing_method.percentage' => 'percentage',
                ]
            ]);
        }

        foreach ($options['additional_properties'] as $key => $opts) {
            $builder->add($key, TextType::class, array_merge($opts, [
                'required' => false,
                'mapped' => false,
                'label' => sprintf('form.local_business.iso_code.%s.%s', $this->country, $key),
                'trim' => true,
            ]));
        }

        $builder->addEventListener(FormEvents::POST_SET_DATA, function (FormEvent $event) use ($options) {
            $form = $event->getForm();
            $localBusiness = $event->getData();

            foreach (array_keys($options['additional_properties']) as $key) {
                if ($form->has($key)) {
                    $form->get($key)->setData($localBusiness->getAdditionalPropertyValue($key));
                }
            }

            if (null !== $localBusiness->getId()) {
                $form->add('imageFile', VichImageType::class, [
                    'required' => false,
                    'download_uri' => false,
                ]);
            }
        });

        $builder->addEventListener(
            FormEvents::POST_SUBMIT,
            function (FormEvent $event) use ($options) {

                $localBusiness = $event->getForm()->getData();

                foreach (array_keys($options['additional_properties']) as $key) {
                    $value = $event->getForm()->get($key)->getData();
                    $localBusiness->setAdditionalProperty($key, $value);
                }
            }
        );

        $builder->addEventListener(
            FormEvents::POST_SUBMIT,
            function (FormEvent $event) {

                $localBusiness = $event->getForm()->getData();

                // Copy shop name into address name
                $localBusiness->getAddress()->setName($localBusiness->getName());

                $telephone = $localBusiness->getTelephone();
                if (null !== $telephone) {
                    $localBusiness->getAddress()->setTelephone($telephone);
                }
            }
        );
    }

    public function configureOptions(OptionsResolver $resolver)
    {
        $additionalProperties = [];

        switch ($this->country) {
            case 'fr':
                $additionalProperties['siret'] = [
                    'constraints' => [
                        new Assert\Luhn(message: 'siret.invalid'),
                        new AssertSiret(),
                    ],
                    'help' => sprintf('form.local_business.iso_code.%s.siret.help', $this->country),
                    'help_html' => true,
                ];
                $additionalProperties['vat_number'] = [];
                $additionalProperties['rcs_number'] = [];
                break;
            case 'ar':
                $additionalProperties['cuit'] = [];
            default:
                break;
        }

        $resolver->setDefaults(array(
            'additional_properties' => $additionalProperties,
        ));
    }
}
