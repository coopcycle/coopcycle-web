<?php

namespace AppBundle\Form\Checkout;

use AppBundle\Form\StripePaymentType;
use AppBundle\Utils\ShippingDateFilter;
use Symfony\Component\Form\Extension\Core\Type\DateType;
use Symfony\Component\Form\Extension\Core\Type\TextareaType;
use Symfony\Component\Form\Extension\Core\Type\TimeType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\Form\FormError;
use Symfony\Component\Form\FormEvents;
use Symfony\Component\Form\FormEvent;
use Symfony\Component\Form\FormInterface;
use Symfony\Component\Validator\Validator\ValidatorInterface;

class CheckoutPaymentType extends AbstractType
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
            ->add('stripePayment', StripePaymentType::class, [
                'mapped' => false,
            ]);

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
    }
}
