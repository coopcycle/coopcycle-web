<?php

namespace AppBundle\Form\Checkout;

use ApiPlatform\Core\Api\IriConverterInterface;
use AppBundle\Form\AddressType;
use AppBundle\LoopEat\Client as LoopEatClient;
use AppBundle\LoopEat\Context as LoopEatContext;
use AppBundle\Utils\PriceFormatter;
use AppBundle\Validator\Constraints\LoopEatOrder;
use GuzzleHttp\Exception\RequestException;
use Lexik\Bundle\JWTAuthenticationBundle\Encoder\JWTEncoderInterface;
use libphonenumber\PhoneNumberFormat;
use Misd\PhoneNumberBundle\Form\Type\PhoneNumberType;
use Sylius\Bundle\PromotionBundle\Form\Type\PromotionCouponToCodeType;
use Sylius\Bundle\PromotionBundle\Validator\Constraints\PromotionSubjectCoupon;
use Sylius\Component\Order\Processor\OrderProcessorInterface;
use Symfony\Component\Form\Extension\Core\Type\CheckboxType;
use Symfony\Component\Form\Extension\Core\Type\DateType;
use Symfony\Component\Form\Extension\Core\Type\NumberType;
use Symfony\Component\Form\Extension\Core\Type\TextareaType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\Extension\Core\Type\TimeType;
use Symfony\Component\Form\Extension\Core\Type\SubmitType;
use Symfony\Component\Form\Extension\Core\Type\HiddenType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\Form\FormError;
use Symfony\Component\Form\FormEvents;
use Symfony\Component\Form\FormEvent;
use Symfony\Component\Form\FormInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use Symfony\Component\Security\Core\Authentication\Token\Storage\TokenStorageInterface;
use Symfony\Component\Translation\TranslatorInterface;
use Symfony\Component\Validator\Validator\ValidatorInterface;
use Misd\PhoneNumberBundle\Validator\Constraints\PhoneNumber as AssertPhoneNumber;

class CheckoutAddressType extends AbstractType
{
    private $tokenStorage;
    private $country;

    public function __construct(
        TokenStorageInterface $tokenStorage,
        TranslatorInterface $translator,
        PriceFormatter $priceFormatter,
        ValidatorInterface $validator,
        LoopEatClient $loopeatClient,
        UrlGeneratorInterface $urlGenerator,
        JWTEncoderInterface $jwtEncoder,
        IriConverterInterface $iriConverter,
        OrderProcessorInterface $orderProcessor,
        LoopEatContext $loopeatContext,
        string $country)
    {
        $this->tokenStorage = $tokenStorage;
        $this->translator = $translator;
        $this->priceFormatter = $priceFormatter;
        $this->validator = $validator;
        $this->loopeatClient = $loopeatClient;
        $this->urlGenerator = $urlGenerator;
        $this->jwtEncoder = $jwtEncoder;
        $this->iriConverter = $iriConverter;
        $this->orderProcessor = $orderProcessor;
        $this->loopeatContext = $loopeatContext;
        $this->country = strtoupper($country);
    }

    public function buildForm(FormBuilderInterface $builder, array $options)
    {
        $builder
            ->add('shippingAddress', AddressType::class, [
                'label' => false,
            ])
            ->add('notes', TextareaType::class, [
                'required' => false,
                'label' => 'form.checkout_address.notes.label',
                'help' => 'form.checkout_address.notes.help',
                'attr' => ['placeholder' => 'form.checkout_address.notes.placeholder']
            ])
            ->add('promotionCoupon', PromotionCouponToCodeType::class, [
                'label' => 'form.checkout_address.promotion_coupon.label',
                'required' => false,
            ])
            ->add('addPromotion', SubmitType::class, [
                'label' => 'form.checkout_address.add_promotion.label'
            ])
            ->add('tipAmount', NumberType::class, [
                'label' => 'form.checkout_address.tip_amount.label',
                'mapped' => false,
                'required' => false,
                'html5' => true,
                'attr'  => array(
                    'min'  => 0,
                    'step' => 0.5,
                ),
                'help' => 'form.checkout_address.tip_amount.help'
            ])
            ->add('addTip', SubmitType::class, [
                'label' => 'form.checkout_address.add_tip.label'
            ]);

        $builder->get('shippingAddress')->addEventListener(FormEvents::POST_SET_DATA, function (FormEvent $event) {

            $form = $event->getForm();

            // Disable shippingAddress.streetAddress
            $this->disableChildForm($form, 'streetAddress');
        });

        $builder->addEventListener(FormEvents::POST_SET_DATA, function (FormEvent $event) {

            $form = $event->getForm();
            $order = $event->getData();

            // Add a "telephone" field when the customer does not have telephone
            if (empty($order->getCustomer()->getPhoneNumber())) {
                $form->add('telephone', PhoneNumberType::class, [
                    'format' => PhoneNumberFormat::NATIONAL,
                    'default_region' => $this->country,
                    'label' => 'form.checkout_address.telephone.label',
                    'mapped' => false,
                    'constraints' => [
                        new AssertPhoneNumber()
                    ],
                ]);
            }

            if ($order->isTakeaway()) {
                $form->remove('shippingAddress');
            }

            $restaurant = $order->getRestaurant();
            $customer = $order->getCustomer();
            $packagingQuantity = $order->getReusablePackagingQuantity();

            if ($order->isEligibleToReusablePackaging() && $restaurant->isDepositRefundOptin()) {

                // FIXME
                // We need to check if $packagingQuantity > 0

                if ($restaurant->isDepositRefundEnabled() && !$restaurant->isLoopeatEnabled()) {
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
                } else if ($restaurant->isLoopeatEnabled() && $customer->hasUser()) {

                    // Use a JWT as the "state" parameter
                    $state = $this->jwtEncoder->encode([
                        'exp' => (new \DateTime('+1 hour'))->getTimestamp(),
                        'sub' => $this->iriConverter->getIriFromItem($customer->getUser()),
                        // Custom claims
                        LoopEatClient::JWT_CLAIM_SUCCESS_REDIRECT =>
                            $this->urlGenerator->generate('loopeat_success', [], UrlGeneratorInterface::ABSOLUTE_URL),
                        LoopEatClient::JWT_CLAIM_FAILURE_REDIRECT =>
                            $this->urlGenerator->generate('loopeat_failure', [], UrlGeneratorInterface::ABSOLUTE_URL),
                    ]);

                    $this->loopeatContext->initialize($customer->getUser());

                    $form->add('reusablePackagingEnabled', CheckboxType::class, [
                        'required' => false,
                        'label' => 'form.checkout_address.reusable_packaging_loopeat_enabled.label',
                        'attr' => [
                            'data-loopeat' => "true",
                            'data-loopeat-credentials' => var_export($customer->getUser()->hasLoopEatCredentials(), true),
                            'data-loopeat-authorize-url' => $this->loopeatClient->getOAuthAuthorizeUrl([
                                'login_hint' => $customer->getUser()->getEmailCanonical(),
                                'state' => $state,
                            ])
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
                    $form->add('isJQuerySubmit', HiddenType::class, [
                        'data' => '0',
                        'mapped' => false,
                    ]);
                }
            }

            // When the restaurant accepts quotes and the customer is allowed,
            // we add another submit button
            $user = $this->tokenStorage->getToken()->getUser();
            if ($restaurant->isQuotesAllowed() && $user->isQuotesAllowed()) {
                $form->add('quote', SubmitType::class, [
                    'label' => 'form.checkout_address.quote.label'
                ]);
            }
        });

        $builder->addEventListener(FormEvents::POST_SUBMIT, function (FormEvent $event) {
            $form = $event->getForm();
            $order = $form->getData();

            $customer = $order->getCustomer();

            if ($form->has('telephone')) {
                $customer->setTelephone($form->get('telephone')->getData());
            }

            if ($form->getClickedButton() && 'addTip' === $form->getClickedButton()->getName()) {

                $tipAmount = $form->get('tipAmount')->getData();
                $order->setTipAmount((int) ($tipAmount * 100));

                $this->orderProcessor->process($order);
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
            new PromotionSubjectCoupon()
        ]);
    }
}
