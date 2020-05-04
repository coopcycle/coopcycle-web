<?php

namespace AppBundle\Form\Checkout;

use AppBundle\Form\AddressType;
use AppBundle\Utils\PriceFormatter;
use libphonenumber\PhoneNumberFormat;
use Misd\PhoneNumberBundle\Form\Type\PhoneNumberType;
use Sylius\Bundle\PromotionBundle\Form\Type\PromotionCouponToCodeType;
use Sylius\Bundle\PromotionBundle\Validator\Constraints\PromotionSubjectCoupon;
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
use Symfony\Component\OptionsResolver\OptionsResolver;
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
        $country)
    {
        $this->tokenStorage = $tokenStorage;
        $this->translator = $translator;
        $this->priceFormatter = $priceFormatter;
        $this->validator = $validator;
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

            // Disable streetAddress, postalCode & addressLocality
            $this->disableChildForm($form, 'streetAddress');
            $this->disableChildForm($form, 'postalCode');
            $this->disableChildForm($form, 'addressLocality');
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

            $restaurant = $order->getRestaurant();

            if ($order->isEligibleToReusablePackaging() && $restaurant->isDepositRefundOptin()) {

                $isLoopEatValid = true;
                if ($order->getRestaurant()->isLoopeatEnabled()) {
                    $violations = $this->validator->validate($order, null, ['loopeat']);
                    $isLoopEatValid = count($violations) === 0;
                }

                if ($isLoopEatValid) {
                    $key = $restaurant->isLoopeatEnabled() ? 'reusable_packaging_loopeat_enabled' : 'reusable_packaging_enabled';

                    $packagingAmount = $order->getReusablePackagingAmount();

                    if ($packagingAmount > 0) {
                        $packagingPrice = sprintf('+ %s', $this->priceFormatter->formatWithSymbol($packagingAmount));
                    } else {
                        $packagingPrice = $this->translator->trans('basics.free');
                    }

                    $form->add('reusablePackagingEnabled', CheckboxType::class, [
                        'required' => false,
                        'label' => sprintf('form.checkout_address.%s.label', $key),
                        'label_translation_parameters' => [
                            '%price%' => $packagingPrice,
                        ],
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
            new PromotionSubjectCoupon()
        ]);
    }
}
