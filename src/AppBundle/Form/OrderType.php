<?php

namespace AppBundle\Form;

use AppBundle\Sylius\Order\OrderTransitions;
use SM\Factory\FactoryInterface as StateMachineFactoryInterface;
use Sylius\Component\Order\Model\OrderInterface;
use Sylius\Component\Payment\PaymentTransitions;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\SubmitType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\Form\FormEvents;
use Symfony\Component\Form\FormEvent;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\Security\Core\Authorization\AuthorizationCheckerInterface;

class OrderType extends AbstractType
{
    private $stateMachineFactory;
    private $authorizationChecker;

    public function __construct(
        StateMachineFactoryInterface $stateMachineFactory,
        AuthorizationCheckerInterface $authorizationChecker)
    {
        $this->stateMachineFactory = $stateMachineFactory;
        $this->authorizationChecker = $authorizationChecker;
    }

    public function buildForm(FormBuilderInterface $builder, array $options)
    {
        $builder
            ->add('customer', CustomerType::class, [
                'label' => 'form.order.customer.label'
            ]);

        $builder->addEventListener(
            FormEvents::POST_SET_DATA,
            function (FormEvent $event) {

                $form = $event->getForm();
                $order = $form->getData();

                $stateMachine = $this->stateMachineFactory->get($order, OrderTransitions::GRAPH);

                if ($this->authorizationChecker->isGranted('ROLE_ADMIN')) {
                    if ($stateMachine->can(OrderTransitions::TRANSITION_ACCEPT)) {
                        $form->add('accept', SubmitType::class, [
                            'label' => 'form.order.accept.label'
                        ]);
                    }
                    if ($stateMachine->can(OrderTransitions::TRANSITION_FULFILL)) {
                        $form->add('fulfill', SubmitType::class, [
                            'label' => 'form.order.fulfill.label'
                        ]);
                    }
                }

                if ($stateMachine->can(OrderTransitions::TRANSITION_CANCEL)) {
                    $form->add('cancel', SubmitType::class, [
                        'label' => 'form.order.cancel.label'
                    ]);
                }

                $customer = $order->getCustomer();

                // var_dump(null === $order->getCustomer());

                if (null !== $customer) {
                    var_dump('YO');
                }

            }
        );

        if ($this->authorizationChecker->isGranted('ROLE_ADMIN')) {
            $builder->addEventListener(
                FormEvents::POST_SET_DATA,
                function (FormEvent $event) {

                    $form = $event->getForm();
                    $order = $form->getData();

                    foreach ($order->getPayments() as $payment) {
                        $stateMachine = $this->stateMachineFactory->get($payment, PaymentTransitions::GRAPH);
                        if ($stateMachine->can(PaymentTransitions::TRANSITION_COMPLETE)) {
                            $form->add(sprintf('payment_%d_complete', $payment->getId()), SubmitType::class, [
                                'label' => 'form.order.payment_complete.label'
                            ]);
                        }
                        if ($stateMachine->can(PaymentTransitions::TRANSITION_REFUND)) {
                            $form->add(sprintf('payment_%d_refund', $payment->getId()), SubmitType::class, [
                                'label' => 'form.order.payment_refund.label'
                            ]);
                        }
                    }
                }
            );
        }

    }

    public function configureOptions(OptionsResolver $resolver)
    {
        $resolver->setDefaults(array(
            'data_class' => OrderInterface::class,
        ));
    }
}
