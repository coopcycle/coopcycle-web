<?php

namespace AppBundle\Form;

use AppBundle\Form\Type\MoneyType;
use SM\Factory\FactoryInterface as StateMachineFactoryInterface;
use Sylius\Component\Order\Model\OrderInterface;
use Sylius\Component\Payment\PaymentTransitions;
use Sylius\Component\Payment\Model\PaymentInterface;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\Form\Extension\Core\Type\TextareaType;
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

                        // If the payment was made with Edenred,
                        // we can only refund the whole amount
                        // https://github.com/coopcycle/coopcycle-web/issues/4615

                        $isEdenred = 'EDENRED' === $payment->getMethod()->getCode();

                        $form->add('amount', MoneyType::class, [
                            'label' => 'form.payment.refund_amount.label',
                            'help' => $isEdenred ? 'form.payment.edenred.refund_amount.help' : 'form.payment.refund_amount.help',
                            'data' => $payment->getRefundAmount(),
                            'disabled' => $isEdenred,
                            'mapped' => false,
                        ]);
                        $form->add('liable', ChoiceType::class, [
                            'choices'  => [
                                'Merchant' => 'merchant',
                                'Platform' => 'platform',
                            ],
                            'label' => 'form.payment.refund_liable.label',
                            'help' => 'form.payment.refund_liable.help',
                            'expanded' => true,
                            'multiple' => false,
                            'mapped' => false,
                            'data' => 'platform',
                        ]);
                        $form->add('comments', TextareaType::class, [
                            'label' => 'form.payment.refund_comment.label',
                            'help' => 'form.payment.refund_comment.help',
                            'mapped' => false,
                            'attr' => ['rows' => '6'],
                            'required' => false,
                        ]);

                        // If the payment was 100% refunded,
                        // we do not show the button
                        if ($payment->getRefundAmount() > 0) {
                            $form->add('refund', SubmitType::class, [
                                'label' => 'form.order.payment_refund.label'
                            ]);
                        }
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
