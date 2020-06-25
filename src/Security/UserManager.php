<?php

namespace AppBundle\Security;

use AppBundle\Entity\Sylius\Customer;
use FOS\UserBundle\Model\UserInterface;
use FOS\UserBundle\Doctrine\UserManager as BaseUserManager;
use libphonenumber\PhoneNumber;
use libphonenumber\PhoneNumberFormat;
use libphonenumber\PhoneNumberUtil;

class UserManager extends BaseUserManager
{
    /**
     * {@inheritdoc}
     */
    public function updateUser(UserInterface $user, $andFlush = true)
    {
        $customer = $user->getCustomer();

        if (null === $customer) {
            $customer = new Customer();
            $user->setCustomer($customer);
        }

        $this->updateCanonicalFields($user);

        $customer->setEmail($user->getEmail());
        $customer->setEmailCanonical($user->getEmailCanonical());
        $customer->setFirstName($user->getGivenName());
        $customer->setLastName($user->getFamilyName());

        $telephone = $user->getTelephone();
        if ($telephone instanceof PhoneNumber) {
            $customer->setPhoneNumber(
                PhoneNumberUtil::getInstance()->format($telephone, PhoneNumberFormat::E164)
            );
        } else {
            $customer->setPhoneNumber($telephone);
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
