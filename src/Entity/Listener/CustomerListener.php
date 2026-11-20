<?php

namespace AppBundle\Entity\Listener;

use AppBundle\Entity\Sylius\Customer;
use Doctrine\ORM\Event\PreUpdateEventArgs;
use RZ\CanonicalEmail\EmailCanonizer;

/**
 * Keeps Customer::$referralCanonicalEmail in sync with the address, so the
 * referral program can tell whether a signup is really a new person or an
 * alias of an account that already exists.
 */
class CustomerListener
{
    public function __construct(private readonly EmailCanonizer $emailCanonizer)
    {
    }

    public function prePersist(Customer $customer): void
    {
        $this->refreshCanonicalEmail($customer);
    }

    public function preUpdate(Customer $customer, PreUpdateEventArgs $event): void
    {
        if (!$event->hasChangedField('email')) {
            return;
        }

        $this->refreshCanonicalEmail($customer);

        // preUpdate runs once the changeset is computed, and this field isn't
        // in it (only "email" changed), so it has to be recomputed to be
        // written -- setNewValue() would reject a field the changeset lacks.
        $entityManager = $event->getObjectManager();
        $entityManager->getUnitOfWork()->recomputeSingleEntityChangeSet(
            $entityManager->getClassMetadata(Customer::class),
            $customer
        );
    }

    private function refreshCanonicalEmail(Customer $customer): void
    {
        $email = $customer->getEmail();

        $customer->setReferralCanonicalEmail(
            empty($email) ? null : $this->emailCanonizer->getCanonicalEmailAddress($email)
        );
    }
}
