<?php

namespace AppBundle\Form\Checkout;

use AppBundle\Form\AddressType;
use AppBundle\Utils\ShippingDateFilter;
use Sylius\Bundle\PromotionBundle\Form\Type\PromotionCouponToCodeType;
use Sylius\Bundle\PromotionBundle\Validator\Constraints\PromotionSubjectCoupon;
use Symfony\Component\Form\Extension\Core\Type\DateType;
use Symfony\Component\Form\Extension\Core\Type\TextareaType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\Extension\Core\Type\TimeType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\Form\FormError;
use Symfony\Component\Form\FormEvents;
use Symfony\Component\Form\FormEvent;
use Symfony\Component\Form\FormInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\Validator\Validator\ValidatorInterface;

class CheckoutAddressType extends AbstractType
{
    private $validator;
    private $shippingDateFilter;

    public function __construct(
        ValidatorInterface $validator,
        ShippingDateFilter $shippingDateFilter)
    {
        $this->validator = $validator;
        $this->shippingDateFilter = $shippingDateFilter;
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
                'attr' => ['placeholder' => 'form.checkout_address.notes.placeholder']
            ])
            ->add('promotionCoupon', PromotionCouponToCodeType::class, [
                'label' => 'form.checkout_address.promotion_coupon.label',
                'required' => false,
            ]);

        $builder->get('shippingAddress')->addEventListener(FormEvents::POST_SET_DATA, function (FormEvent $event) {
            $form = $event->getForm();

            // Disable streetAddress, postalCode & addressLocality
            $this->disableChildForm($form, 'streetAddress');
            $this->disableChildForm($form, 'postalCode');
            $this->disableChildForm($form, 'addressLocality');
        });

        // This listener may add a field to modify the shipping date,
        // if the shipping date is now expired
        $builder->addEventListener(FormEvents::POST_SET_DATA, function (FormEvent $event) {
            $form = $event->getForm();
            $data = $event->getData();

            $violations = $this->validator->validate($data);

            $hasShippedAt = false;
            $shippedAtErrorMessage = null;
            foreach ($violations as $violation) {
                if ('shippedAt' === $violation->getPropertyPath()) {
                    $hasShippedAt = true;
                    $shippedAtErrorMessage = $violation->getMessage();
                    break;
                }
            }

            if ($hasShippedAt) {

                $availabilities = $data->getRestaurant()->getAvailabilities();
                $availabilities = array_filter($availabilities, function ($date) use ($data) {
                    return $this->shippingDateFilter->accept($data, new \DateTime($date));
                });
                $availabilities = array_values($availabilities);

                $form->add('shippedAt', DateTimeType::class, [
                    'label' => false,
                    'choices' => $availabilities,
                    'data' => $data->getShippedAt(),
                    'help_message' => $shippedAtErrorMessage,
                ]);
            }
        });

        $builder->addEventListener(FormEvents::POST_SUBMIT, function (FormEvent $event) {

            $order = $event->getForm()->getData();

            $shippingAddress = $order->getShippingAddress();
            $customer = $order->getCustomer();

            // Copy customer data into address
            $shippingAddress->setFirstName($customer->getGivenName());
            $shippingAddress->setLastName($customer->getFamilyName());
            $shippingAddress->setTelephone($customer->getTelephone());
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
