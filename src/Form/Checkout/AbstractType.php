<?php

namespace AppBundle\Form\Checkout;

use AppBundle\DataType\TsRange;
use AppBundle\Entity\Sylius\Order;
use AppBundle\Utils\OrderTimeHelper;
use AppBundle\Validator\Constraints\ShippingTimeRangeJump;
use Symfony\Component\Form\AbstractType as BaseAbstractType;
use Symfony\Component\Form\Extension\Core\Type\HiddenType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\Form\FormEvents;
use Symfony\Component\Form\FormEvent;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\Validator\Constraints as Assert;
use Symfony\Component\Validator\Context\ExecutionContextInterface;

class AbstractType extends BaseAbstractType
{
    protected $orderTimeHelper;

    public function __construct(OrderTimeHelper $orderTimeHelper)
    {
        $this->orderTimeHelper = $orderTimeHelper;
    }

    public function buildForm(FormBuilderInterface $builder, array $options)
    {
        $builder->addEventListener(FormEvents::POST_SET_DATA, function (FormEvent $event) {

            $form = $event->getForm();
            $order = $event->getData();

            $range =
                $order->getShippingTimeRange() ?? $this->orderTimeHelper->getShippingTimeRange($order);

            // Don't forget that $range may be NULL
            $data = $range ? implode(' - ', [
                $range->getLower()->format(\DateTime::ATOM),
                $range->getUpper()->format(\DateTime::ATOM),
            ]) : '';

            $form->add('shippingTimeRange', HiddenType::class, [
                'data' => $data,
                'mapped' => false,
                'constraints' => [
                    new Assert\Callback([
                        'callback' => function ($value, ExecutionContextInterface $context) use ($order) {

                            if (null !== $order->getShippingTimeRange()) {
                                return;
                            }

                            // This happens when submitting a partial form
                            // (for ex. when adding tips)
                            if (null === $value) {
                                return;
                            }

                            $displayed  = TsRange::parse($value);
                            $calculated = $this->orderTimeHelper->getShippingTimeRange($order);

                            $validator = $context->getValidator();

                            $violations = $validator->validate([
                                $displayed, $calculated
                            ], new ShippingTimeRangeJump());

                            foreach ($violations as $violation) {
                                $context->addViolation($violation->getMessage());
                            }
                        }
                    ]),
                ],
            ]);
        });
    }

    public function configureOptions(OptionsResolver $resolver)
    {
        $resolver->setDefaults(array(
            'data_class' => Order::class,
        ));
    }
}
