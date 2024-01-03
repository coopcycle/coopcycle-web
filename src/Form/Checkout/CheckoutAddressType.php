<?php

namespace AppBundle\Form\Checkout;

use AppBundle\Entity\Nonprofit;
use AppBundle\Form\AddressType;
use AppBundle\LoopEat\Context as LoopEatContext;
use AppBundle\LoopEat\ContextInitializer as LoopEatContextInitializer;
use AppBundle\LoopEat\GuestCheckoutAwareAdapter as LoopEatAdapter;
use AppBundle\Dabba\Client as DabbaClient;
use AppBundle\Dabba\Context as DabbaContext;
use AppBundle\Dabba\GuestCheckoutAwareAdapter as DabbaAdapter;
use AppBundle\Utils\OrderTimeHelper;
use AppBundle\Utils\PriceFormatter;
use AppBundle\Validator\Constraints\DabbaOrder;
use AppBundle\Validator\Constraints\LoopEatOrder;
use Doctrine\ORM\EntityRepository;
use Symfony\Bridge\Doctrine\Form\Type\EntityType;
use Symfony\Component\Form\Extension\Core\Type\CheckboxType;
use Symfony\Component\Form\Extension\Core\Type\HiddenType;
use Symfony\Component\Form\Extension\Core\Type\NumberType;
use Symfony\Component\Form\Extension\Core\Type\SubmitType;
use Symfony\Component\Form\Extension\Core\Type\TextareaType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\Form\FormEvent;
use Symfony\Component\Form\FormEvents;
use Symfony\Component\Form\FormInterface;
use Symfony\Component\HttpFoundation\Session\SessionInterface;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\Validator\Constraints as Assert;
use Symfony\Contracts\Translation\TranslatorInterface;

class CheckoutAddressType extends AbstractType
{
    private $translator;
    private $priceFormatter;
    private $loopeatContext;
    private $requestStack;

    public function __construct(
        TranslatorInterface $translator,
        PriceFormatter $priceFormatter,
        OrderTimeHelper $orderTimeHelper,
        LoopEatContext $loopeatContext,
        LoopEatContextInitializer $loopeatContextInitializer,
        RequestStack $requestStack,
        DabbaClient $dabbaClient,
        DabbaContext $dabbaContext,
        bool $nonProfitsEnabled,
        string $enBoitLePlatUrl)
    {
        $this->translator = $translator;
        $this->priceFormatter = $priceFormatter;
        $this->loopeatContext = $loopeatContext;
        $this->loopeatContextInitializer = $loopeatContextInitializer;
        $this->requestStack = $requestStack;
        $this->dabbaClient = $dabbaClient;
        $this->dabbaContext = $dabbaContext;
        $this->nonProfitsEnabled = $nonProfitsEnabled;
        $this->enBoitLePlatUrl = $enBoitLePlatUrl;

        parent::__construct($orderTimeHelper);
    }

    public function buildForm(FormBuilderInterface $builder, array $options)
    {
        parent::buildForm($builder, $options);

        $builder
            ->add('shippingAddress', AddressType::class, [
                'label' => false,
            ])
            ->add('notes', TextareaType::class, [
                'required' => false,
                'label' => 'form.checkout_address.notes.label',
                'help' => 'form.checkout_address.notes.help',
                'attr' => ['placeholder' => 'form.checkout_address.notes.placeholder']
            ]);

        $builder->get('shippingAddress')->addEventListener(FormEvents::POST_SET_DATA, function (FormEvent $event) {

            $form = $event->getForm();

            // Disable shippingAddress.streetAddress
            $this->disableChildForm($form, 'streetAddress');
        });

        $builder->addEventListener(FormEvents::POST_SET_DATA, function (FormEvent $event) {

            $form = $event->getForm();
            $order = $event->getData();

            $form->add('customer', CheckoutCustomerType::class, [
                'label' => false,
                // We need to use mapped = false
                // because the form may be submitted "partially"
                // (for example, when toggling reusable packaging)
                'mapped' => false,
                'constraints' => [
                    new Assert\Valid(),
                ],
                'data' => $order->getCustomer(),
            ]);

            if ($order->isTakeaway()) {
                $form->remove('shippingAddress');
            }

            $restaurant = $order->getRestaurant();
            $customer = $order->getCustomer();
            $packagingQuantity = $order->getReusablePackagingQuantity();

            if ($order->isEligibleToReusablePackaging()) {

                $supportsLoopEat = $restaurant->isLoopeatEnabled() && $restaurant->hasLoopEatCredentials();
                $supportsDabba = $restaurant->isDabbaEnabled(); // TODO Check if Dabba code is configured

                // FIXME
                // We need to check if $packagingQuantity > 0

                if (!$order->isMultiVendor() && $supportsLoopEat) {

                    $this->loopeatContextInitializer->initialize($order, $this->loopeatContext);

                    $form->add('reusablePackagingEnabled', CheckboxType::class, [
                        'required' => false,
                        'label' => 'form.checkout_address.reusable_packaging_loopeat_enabled.label',
                        'attr' => [
                            'data-loopeat' => 'true',
                        ],
                    ]);

                } elseif (!$order->isMultiVendor() && $supportsDabba) {

                    $this->dabbaContext->initialize();

                    $dabbaAdapter = new DabbaAdapter($order, $this->requestStack->getSession());

                    $dabbaAuthorizeParams = [
                        'state' => $this->dabbaClient->createStateParamForOrder($order),
                    ];

                    $form->add('reusablePackagingEnabled', CheckboxType::class, [
                        'required' => false,
                        'label' => 'form.checkout_address.reusable_packaging_dabba_enabled.label',
                        'attr' => [
                            'data-dabba' => 'true',
                            'data-dabba-credentials' => var_export($dabbaAdapter->hasDabbaCredentials(), true),
                            'data-dabba-authorize-url' => $this->dabbaClient->getOAuthAuthorizeUrl($dabbaAuthorizeParams),
                            'data-dabba-expected-wallet' => $packagingQuantity * $this->dabbaContext->getUnitPrice(),
                        ],
                    ]);

                    /*
                    $form->add('reusablePackagingPledgeReturn', NumberType::class, [
                        'required' => false,
                        'html5' => true,
                        'label' => 'form.checkout_address.reusable_packaging_dabba_returns.label',
                        // WARNING
                        // Need to use a string here, or it won't work as expected
                        // https://github.com/symfony/symfony/issues/12499
                        'empty_data' => '0',
                    ]);
                    */

                } elseif (!$order->isMultiVendor() && $restaurant->isVytalEnabled()) {

                    $form->add('reusablePackagingEnabled', CheckboxType::class, [
                        'required' => false,
                        'label' => 'form.checkout_address.reusable_packaging_vytal_enabled.label',
                        'attr' => [
                            'data-vytal' => 'true',
                        ],
                    ]);

                } elseif (!$order->isMultiVendor() && $restaurant->isEnBoitLePlatEnabled()) {

                    $form->add('reusablePackagingEnabled', CheckboxType::class, [
                        'required' => false,
                        'label' => 'form.checkout_address.reusable_packaging_en_boite_le_plat_enabled.label',
                        'label_translation_parameters' => [
                            '%url%' => $this->enBoitLePlatUrl
                        ],
                        'label_html' => true,
                        'attr' => [
                            'data-en-boite-le-plat' => 'true',
                        ],
                    ]);

                } elseif ($restaurant->isDepositRefundEnabled() && $restaurant->isDepositRefundOptin()) {

                    $packagingAmount = $order->getReusablePackagingAmount();

                    if ($packagingAmount > 0) {
                        $packagingPrice = sprintf('+ %s', $this->priceFormatter->formatWithSymbol($packagingAmount));
                    } else {
                        $packagingPrice = $this->translator->trans('basics.free');
                    }

                    $form->add('reusablePackagingEnabled', CheckboxType::class, [
                        'required' => false,
                        'label' => 'form.checkout_address.reusable_packaging_enabled.label',
                        'label_translation_parameters' => [
                            '%price%' => $packagingPrice,
                        ],
                        'attr' => [
                            'data-packaging-amount' => $packagingAmount
                        ],
                    ]);
                }
            }

            // When the restaurant accepts quotes and the customer is allowed,
            // we add another submit button
            if (!$order->isMultiVendor() &&
                $restaurant->isQuotesAllowed() && null !== $customer && $customer->hasUser() && $customer->getUser()->isQuotesAllowed()) {
                $form->add('quote', SubmitType::class, [
                    'label' => 'form.checkout_address.quote.label'
                ]);
            }
        });

        if ($this->nonProfitsEnabled) {
            $builder->add('nonprofit', EntityType::class, [
                'class' => Nonprofit::class,
                'choice_label' => 'name',
                'query_builder' => function (EntityRepository $er) {
                    return $er->createQueryBuilder('u')
                        ->where('u.enabled = true');
                },
                'expanded' => false,
                'multiple' => false,
                'required' => false,
                'placeholder' => 'form.checkout_address.nonprofit.placeholder',
            ]);
        }
    }

    private function disableChildForm(FormInterface $form, $name)
    {
        $child = $form->get($name);

        $config = $child->getConfig();
        $options = $config->getOptions();
        $options['disabled'] = true;

        $form->add($name, get_class($config->getType()->getInnerType()), $options);
    }

    public function configureOptions(OptionsResolver $resolver)
    {
        parent::configureOptions($resolver);

        $resolver->setDefault('constraints', [
            new LoopEatOrder(),
            new DabbaOrder(),
        ]);
    }
}
