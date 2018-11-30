<?php

namespace AppBundle\Form\Checkout;

use AppBundle\Form\AddressType;
use Symfony\Component\Form\Extension\Core\Type\DateType;
use Symfony\Component\Form\Extension\Core\Type\TextareaType;
use Symfony\Component\Form\Extension\Core\Type\TimeType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\Form\FormError;
use Symfony\Component\Form\FormEvents;
use Symfony\Component\Form\FormEvent;
use Symfony\Component\Form\FormInterface;
use Symfony\Component\Validator\Validator\ValidatorInterface;

class CheckoutAddressType extends AbstractType
{
    private $validator;

    public function __construct(ValidatorInterface $validator)
    {
        $this->validator = $validator;
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
                $form->add('shippedAt', DateTimeType::class, [
                    'label' => false,
                    'choices' => $data->getRestaurant()->getAvailabilities(),
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
}
