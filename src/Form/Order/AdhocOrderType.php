<?php

namespace AppBundle\Form\Order;

use AppBundle\DataType\TsRange;
use AppBundle\Form\AddressType;
use AppBundle\Sylius\Order\OrderInterface;
use AppBundle\Utils\DateUtils;
use AppBundle\Form\Type\AsapChoiceLoader;
use AppBundle\Service\TimeRegistry;
use Sylius\Component\Order\Processor\OrderProcessorInterface;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\CheckboxType;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\Form\Extension\Core\Type\HiddenType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\Form\FormEvents;
use Symfony\Component\Form\FormEvent;
use Symfony\Component\OptionsResolver\OptionsResolver;

class AdhocOrderType extends AbstractType
{
    private $timeRegistry;

    public function __construct(TimeRegistry $timeRegistry)
    {
        $this->timeRegistry = $timeRegistry;
    }

    public function buildForm(FormBuilderInterface $builder, array $options)
    {
        $builder
            ->add('shippingAddress', AddressType::class, [
                'label' => false
            ])
            ;

        $builder->addEventListener(FormEvents::POST_SET_DATA, function (FormEvent $event) {

            $form = $event->getForm();
            $cart = $event->getData();

            $vendor = $cart->getVendor();
            $fulfillmentMethod = $cart->getFulfillmentMethodObject();

            $choiceLoader = new AsapChoiceLoader(
                $fulfillmentMethod->getOpeningHours(),
                $this->timeRegistry,
                $vendor->getClosingRules(),
                $fulfillmentMethod->getOrderingDelayMinutes(),
                $fulfillmentMethod->getOption('range_duration', 10),
                $fulfillmentMethod->isPreOrderingAllowed()
            );

            $form->add('shippingTimeRange', ChoiceType::class, [
                'choice_loader' => $choiceLoader,
                'choice_label' => function ($choice, $key, $value) {
                    return (string) $choice;
                },
                // 'mapped' => false,
            ]);
        });

    }

    public function configureOptions(OptionsResolver $resolver)
    {
        $resolver
            ->setDefaults([
                'data_class' => OrderInterface::class,
            ]);
    }
}
