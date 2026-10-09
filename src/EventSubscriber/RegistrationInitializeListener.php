<?php

namespace AppBundle\EventSubscriber;

use AppBundle\Entity\User;
use AppBundle\Service\Referral\ReferralManager;
use Nucleos\ProfileBundle\NucleosProfileEvents;
use Nucleos\UserBundle\Event\FilterUserResponseEvent;
use Nucleos\UserBundle\Event\FormEvent;
use Nucleos\ProfileBundle\Event\GetResponseRegistrationEvent;
use Nucleos\ProfileBundle\Event\UserFormEvent;
use Nucleos\UserBundle\Util\Canonicalizer as CanonicalizerInterface;
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
        string $secret,
        private ReferralManager $referralManager)
    {
        $this->orderRepository = $orderRepository;
        $this->customerRepository = $customerRepository;
        $this->canonicalizer = $canonicalizer;
        $this->secret = $secret;
    }

    /**
     * @return array
     */
    public static function getSubscribedEvents()
    {
        return array(
            NucleosProfileEvents::REGISTRATION_INITIALIZE => 'onRegistrationInitialize',
            NucleosProfileEvents::REGISTRATION_SUCCESS => 'onRegistrationSuccess',
            NucleosProfileEvents::REGISTRATION_COMPLETED => 'onRegistrationCompleted',
        );
    }

    public function onRegistrationInitialize(GetResponseRegistrationEvent $event)
    {
        $request = $event->getRequest();

        $customer = $this->getCustomerFromSource($request);

        if (null === $customer) {
            return;
        }

        $user = $event->getUser();

        $user->setEmail($customer->getEmailCanonical());
    }

    public function onRegistrationSuccess(UserFormEvent $event)
    {
        $request = $event->getRequest();
        $form = $event->getForm();
        $user = $event->getUser();

        // Stashed on the request so onRegistrationCompleted() can read it
        // once the user/customer are actually persisted -- the referral
        // code field isn't mapped to User/Customer, and at this point in
        // the registration flow updateUser() hasn't run yet.
        if ($form->has('referralCode')) {
            $request->attributes->set('_referral_code', $form->get('referralCode')->getData());
        }

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

    public function onRegistrationCompleted(FilterUserResponseEvent $event): void
    {
        $user = $event->getUser();

        if (!$user instanceof User) {
            return;
        }

        $referralCode = $event->getRequest()?->attributes->get('_referral_code');

        $this->referralManager->registerPendingReferral($user, $referralCode);
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
