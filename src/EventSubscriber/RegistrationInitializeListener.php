<?php

namespace AppBundle\EventSubscriber;

use FOS\UserBundle\FOSUserEvents;
use FOS\UserBundle\Event\FormEvent;
use FOS\UserBundle\Event\GetResponseUserEvent;
use FOS\UserBundle\Util\CanonicalizerInterface;
use Hashids\Hashids;
use Sylius\Component\Resource\Repository\RepositoryInterface;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\HttpFoundation\RequestStack;

/**
 * Listener responsible for pre-filling form
 */
class RegistrationInitializeListener implements EventSubscriberInterface
{
    private $orderRepository;
    private $customerRepository;
    private $canonicalizer;
    private $requestStack;
    private $secret;

    public function __construct(
        RepositoryInterface $orderRepository,
        RepositoryInterface $customerRepository,
        CanonicalizerInterface $canonicalizer,
        RequestStack $requestStack,
        string $secret)
    {
        $this->orderRepository = $orderRepository;
        $this->customerRepository = $customerRepository;
        $this->canonicalizer = $canonicalizer;
        $this->requestStack = $requestStack;
        $this->secret = $secret;
    }

    /**
     * {@inheritdoc}
     */
    public static function getSubscribedEvents()
    {
        return array(
            FOSUserEvents::REGISTRATION_INITIALIZE => 'onRegistrationInitialize',
            FOSUserEvents::REGISTRATION_SUCCESS => 'onRegistrationSuccess',
        );
    }

    public function onRegistrationInitialize(GetResponseUserEvent $event)
    {
        $request = $this->requestStack->getMasterRequest();

        if (!$request) {
            return;
        }

        if (!$request->query->has('source')) {
            return;
        }

        $hashids = new Hashids($this->secret, 16);
        $decoded = $hashids->decode($request->query->get('source'));

        if (count($decoded) !== 1) {
            return;
        }

        $id = current($decoded);

        $order = $this->orderRepository->find($id);

        if (null === $order) {
            return;
        }

        $user = $event->getUser();
        $customer = $order->getCustomer();

        $user->setEmail($customer->getEmailCanonical());
        $user->setGivenName($customer->getFirstName());
        $user->setFamilyName($customer->getLastName());
        $user->setTelephone($customer->getPhoneNumber());

        $user->setCustomer($customer);
    }

    public function onRegistrationSuccess(FormEvent $event)
    {
        $form = $event->getForm();

        $emailCanonical = $this->canonicalizer->canonicalize($form->get('email')->getData());

        $user = $form->getData();
        $customer = $this->customerRepository->findOneBy(['emailCanonical' => $emailCanonical]);

        if (null !== $customer) {
            $user->setCustomer($customer);
        }
    }
}
