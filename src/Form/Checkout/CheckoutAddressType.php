<?php

namespace AppBundle\Form\Checkout;

use AppBundle\DataType\TsRange;
use AppBundle\Form\AddressType;
use AppBundle\LoopEat\Client as LoopEatClient;
use AppBundle\LoopEat\Context as LoopEatContext;
use AppBundle\LoopEat\GuestCheckoutAwareAdapter as LoopEatAdapter;
use AppBundle\Utils\OrderTimeHelper;
use AppBundle\Utils\PriceFormatter;
use AppBundle\Validator\Constraints\LoopEatOrder;
use Symfony\Component\Form\Extension\Core\Type\CheckboxType;
use Symfony\Component\Form\Extension\Core\Type\DateType;
use Symfony\Component\Form\Extension\Core\Type\NumberType;
use Symfony\Component\Form\Extension\Core\Type\TextareaType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\Extension\Core\Type\TimeType;
use Symfony\Component\Form\Extension\Core\Type\SubmitType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\Form\FormError;
use Symfony\Component\Form\FormEvents;
use Symfony\Component\Form\FormEvent;
use Symfony\Component\Form\FormInterface;
use Symfony\Component\HttpFoundation\Session\SessionInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Contracts\Translation\TranslatorInterface;
use Symfony\Component\Validator\Constraints as Assert;

class CheckoutAddressType extends AbstractType
{
    private $translator;
    private $priceFormatter;
    private $loopeatClient;
    private $loopeatContext;
    private $session;
    private $loopeatOAuthFlow;

    public function __construct(
        TranslatorInterface $translator,
        PriceFormatter $priceFormatter,
        OrderTimeHelper $orderTimeHelper,
        LoopEatClient $loopeatClient,
        LoopEatContext $loopeatContext,
        SessionInterface $session,
        string $loopeatOAuthFlow)
    {
        $this->translator = $translator;
        $this->priceFormatter = $priceFormatter;
        $this->loopeatClient = $loopeatClient;
        $this->loopeatContext = $loopeatContext;
        $this->session = $session;
        $this->loopeatOAuthFlow = $loopeatOAuthFlow;

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

                // FIXME
                // We need to check if $packagingQuantity > 0

                if (!$order->isMultiVendor() && $restaurant->isLoopeatEnabled() && $restaurant->hasLoopEatCredentials()) {

                    $this->loopeatContext->initialize();

                    $loopeatAdapter = new LoopEatAdapter($order, $this->session);

                    $loopeatAuthorizeParams = [
                        'state' => $this->loopeatClient->createStateParamForOrder($order),
                    ];

                    if (null !== $customer && !empty($customer->getEmailCanonical())) {
                        $loopeatAuthorizeParams['login_hint'] = $customer->getEmailCanonical();
                    }

                    $form->add('reusablePackagingEnabled', CheckboxType::class, [
                        'required' => false,
                        'label' => 'form.checkout_address.reusable_packaging_loopeat_enabled.label',
                        'attr' => [
                            'data-loopeat' => 'true',
                            'data-loopeat-credentials' => var_export($loopeatAdapter->hasLoopEatCredentials(), true),
                            'data-loopeat-authorize-url' => $this->loopeatClient->getOAuthAuthorizeUrl($loopeatAuthorizeParams),
                            'data-loopeat-oauth-flow' => $this->loopeatOAuthFlow,
                        ],
                    ]);
                    $form->add('reusablePackagingPledgeReturn', NumberType::class, [
                        'required' => false,
                        'html5' => true,
                        'label' => 'form.checkout_address.reusable_packaging_loopeat_returns.label',
                        // WARNING
                        // Need to use a string here, or it won't work as expected
                        // https://github.com/symfony/symfony/issues/12499
                        'empty_data' => '0',
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
        ]);
    }
}
