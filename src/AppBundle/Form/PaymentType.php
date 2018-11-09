<?php

namespace AppBundle\Form;

use SM\Factory\FactoryInterface as StateMachineFactoryInterface;
use Sylius\Component\Order\Model\OrderInterface;
use Sylius\Component\Payment\PaymentTransitions;
use Sylius\Component\Payment\Model\PaymentInterface;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\CheckboxType;
use Symfony\Component\Form\Extension\Core\Type\MoneyType;
use Symfony\Component\Form\Extension\Core\Type\SubmitType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\Form\FormEvents;
use Symfony\Component\Form\FormEvent;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\Security\Core\Authorization\AuthorizationCheckerInterface;

class PaymentType extends AbstractType
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
        $builder->addEventListener(
            FormEvents::POST_SET_DATA,
            function (FormEvent $event) {

                $form = $event->getForm();
                $payment = $form->getData();

                $stateMachine = $this->stateMachineFactory->get($payment, PaymentTransitions::GRAPH);

                if ($stateMachine->can(PaymentTransitions::TRANSITION_REFUND)) {

                    if ($this->authorizationChecker->isGranted('ROLE_ADMIN')) {

                        $form->add('amount', MoneyType::class, [
                            'label' => 'form.payment.refund_amount.label',
                            'data' => $payment->getRefundAmount(),
                            'divisor' => 100,
                            'mapped' => false,
                        ]);

                        $form->add('refundApplicationFee', CheckboxType::class, [
                            'label' => 'form.payment.refund_application_fee.label',
                            'data' => true,
                            'mapped' => false,
                            'required' => false,
                        ]);

                        $form->add('refund', SubmitType::class, [
                            'label' => 'form.order.payment_refund.label'
                        ]);
                    }
                }
            }
        );
    }

    public function configureOptions(OptionsResolver $resolver)
    {
        $resolver->setDefaults(array(
            'data_class' => PaymentInterface::class,
        ));
    }
}
