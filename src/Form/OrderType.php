<?php

namespace AppBundle\Form;

use AppBundle\Sylius\Order\OrderTransitions;
use SM\Factory\FactoryInterface as StateMachineFactoryInterface;
use Sylius\Component\Order\Model\OrderInterface;
use Sylius\Component\Payment\Model\PaymentInterface;
use Sylius\Component\Payment\PaymentTransitions;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\CollectionType;
use Symfony\Component\Form\Extension\Core\Type\SubmitType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\Form\FormEvents;
use Symfony\Component\Form\FormEvent;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\Security\Core\Authorization\AuthorizationCheckerInterface;
use Symfony\Contracts\Translation\TranslatorInterface;

class OrderType extends AbstractType
{
    private $stateMachineFactory;
    private $authorizationChecker;
    private $translator;

    public function __construct(
        StateMachineFactoryInterface $stateMachineFactory,
        AuthorizationCheckerInterface $authorizationChecker,
        TranslatorInterface $translator)
    {
        $this->stateMachineFactory = $stateMachineFactory;
        $this->authorizationChecker = $authorizationChecker;
        $this->translator = $translator;
    }

    public function buildForm(FormBuilderInterface $builder, array $options)
    {
        $builder->add('payments', CollectionType::class, [
            'entry_type' => PaymentType::class,
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
                            'label' => 'form.order.fulfill.label',
                        ]);
                    }
                    if ($stateMachine->can(OrderTransitions::TRANSITION_REFUSE)) {

                        $attr = [];

                        $completedPayment =
                            $order->getLastPayment(PaymentInterface::STATE_COMPLETED);

                        if (null !== $completedPayment && $completedPayment->isGiropay()) {
                            $attr['data-message'] = $this->translator->trans('form.order.refuse.refund.alert');
                        }

                        $form->add('refuse', SubmitType::class, [
                            'label' => 'form.order.refuse.label',
                            'attr' => $attr
                        ]);

                    } elseif ($stateMachine->can(OrderTransitions::TRANSITION_CANCEL)) {
                        $form->add('cancel', SubmitType::class, [
                            'label' => 'basics.cancel'
                        ]);
                    }
                }
            }
        );
    }

    public function configureOptions(OptionsResolver $resolver)
    {
        $resolver->setDefaults(array(
            'data_class' => OrderInterface::class,
        ));
    }
}
