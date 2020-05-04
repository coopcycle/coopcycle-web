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
    public function updateUser(UserInterface $user, $andFlush = true)
    {
        $customer = $user->getCustomer();

        if (null === $customer) {

            $this->updateCanonicalFields($user);

            $customer = new Customer();
            $customer->setEmail($user->getEmail());
            $customer->setEmailCanonical($user->getEmailCanonical());
            $customer->setFirstName($user->getGivenName());
            $customer->setLastName($user->getFamilyName());

            // TODO set telephone

            $user->setCustomer($customer);
        }

        return parent::updateUser($user, $andFlush);
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
