<?php

namespace AppBundle\EventSubscriber;

use Nucleos\ProfileBundle\NucleosProfileEvents;
use Nucleos\UserBundle\Event\FormEvent;
use Nucleos\ProfileBundle\Event\GetResponseRegistrationEvent;
use Nucleos\ProfileBundle\Event\UserFormEvent;
use Nucleos\UserBundle\Util\CanonicalizerInterface;
use Hashids\Hashids;
use Sylius\Component\Resource\Repository\RepositoryInterface;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\HttpFoundation\Request;

/**
 * Listener responsible for pre-filling form
 */
class RegistrationInitializeListener implements EventSubscriberInterface
{
    private $orderRepository;
    private $customerRepository;
    private $canonicalizer;
    private $secret;

    public function __construct(
        RepositoryInterface $orderRepository,
        RepositoryInterface $customerRepository,
        CanonicalizerInterface $canonicalizer,
        string $secret)
    {
        $this->orderRepository = $orderRepository;
        $this->customerRepository = $customerRepository;
        $this->canonicalizer = $canonicalizer;
        $this->secret = $secret;
    }

    /**
     * {@inheritdoc}
     */
    public static function getSubscribedEvents()
    {
        return array(
            NucleosProfileEvents::REGISTRATION_INITIALIZE => 'onRegistrationInitialize',
            NucleosProfileEvents::REGISTRATION_SUCCESS => 'onRegistrationSuccess',
        );
    }

    public function onRegistrationInitialize(GetResponseRegistrationEvent $event)
    {
        $request = $event->getRequest();

        $customer = $this->getCustomerFromSource($request);

        if (null === $customer) {
            return;
        }

        $registration = $event->getRegistration();

        $registration->setEmail($customer->getEmailCanonical());
    }

    public function onRegistrationSuccess(UserFormEvent $event)
    {
        $request = $event->getRequest();
        $form = $event->getForm();
        $user = $event->getUser();

        $customer = $this->getCustomerFromSource($request);

        if (null !== $customer) {
            $user->setCustomer($customer);
            return;
        }

        $emailCanonical = $this->canonicalizer->canonicalize($form->get('email')->getData());
        $customer = $this->customerRepository->findOneBy(['emailCanonical' => $emailCanonical]);

        if (null !== $customer) {
            $user->setCustomer($customer);
        }
    }

    private function getCustomerFromSource(Request $request)
    {
        if (!$request->query->has('source')) {
            return null;
        }

        $hashids = new Hashids($this->secret, 16);
        $decoded = $hashids->decode($request->query->get('source'));

        if (count($decoded) !== 1) {
            return null;
        }

        $id = current($decoded);

        $order = $this->orderRepository->find($id);

        if (null === $order) {
            return null;
        }

        return $order->getCustomer();
    }
}
