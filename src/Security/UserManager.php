<?php

namespace AppBundle\Security;

use AppBundle\Entity\Sylius\Customer;
use FOS\UserBundle\Model\UserInterface;
use FOS\UserBundle\Doctrine\UserManager as BaseUserManager;

class UserManager extends BaseUserManager
{
    /**
     * {@inheritdoc}
     */
    public function createUser()
    {
        $user = parent::createUser();

        $user->setCustomer(new Customer());

        return $user;
    }

    /**
     * {@inheritdoc}
     */
    public function updateCanonicalFields(UserInterface $user)
    {
        parent::updateCanonicalFields($user);

        $customer = $user->getCustomer();
        if (null !== $customer) {
            $emailCanonical = $this->getCanonicalFieldsUpdater()->canonicalizeEmail($customer->getEmail());
            $customer->setEmailCanonical($emailCanonical);
        }
    }

    /**
     * @param string $role
     * @return array
     */
    public function findUsersByRole($role)
    {
        $qb = $this->getRepository()
            ->createQueryBuilder('u')
            ->where('u.roles LIKE :roles')
            ->setParameter('roles', sprintf('%%%s%%', $role));

        return $qb->getQuery()->getResult();
    }
}
