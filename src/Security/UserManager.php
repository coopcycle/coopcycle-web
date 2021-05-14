<?php

namespace AppBundle\Security;

use AppBundle\Entity\Sylius\Customer;
use Doctrine\Persistence\ObjectManager;
use Nucleos\UserBundle\Model\UserInterface;
use Nucleos\UserBundle\Model\UserManagerInterface;
use Nucleos\UserBundle\Doctrine\UserManager as DoctrineUserManager;
use Nucleos\UserBundle\Util\CanonicalFieldsUpdater;

class UserManager implements UserManagerInterface
{
    private $canonicalFieldsUpdater;

    public function __construct(
        DoctrineUserManager $decorated,
        ObjectManager $objectManager,
        CanonicalFieldsUpdater $canonicalFieldsUpdater)
    {
        $this->decorated = $decorated;
        $this->objectManager = $objectManager;
        $this->canonicalFieldsUpdater = $canonicalFieldsUpdater;
    }

    /**
     * {@inheritdoc}
     */
    public function createUser(): UserInterface
    {
        $user = $this->decorated->createUser();

        $user->setCustomer(new Customer());

        return $user;
    }

    /**
     * {@inheritdoc}
     */
    public function updateCanonicalFields(UserInterface $user): void
    {
        $this->decorated->updateCanonicalFields($user);

        $customer = $user->getCustomer();
        if (null !== $customer) {
            $emailCanonical = $this->canonicalFieldsUpdater->canonicalizeEmail($customer->getEmail());
            $customer->setEmailCanonical($emailCanonical);
        }
    }

    /**
     * @param string $role
     * @return array
     */
    public function findUsersByRole($role)
    {
        $qb = $this->objectManager->getRepository($this->decorated->getClass())
            ->createQueryBuilder('u')
            ->where('u.roles LIKE :roles')
            ->setParameter('roles', sprintf('%%%s%%', $role));

        return $qb->getQuery()->getResult();
    }

    public function deleteUser(UserInterface $user): void
    {
        $this->decorated->deleteUser($user);
    }

    public function getClass(): string
    {
        return $this->decorated->getClass();
    }

    public function findUserBy(array $criteria): ?UserInterface
    {
        return $this->decorated->findUserBy($criteria);
    }

    public function findUsers(): array
    {
        return $this->decorated->findUsers();
    }

    public function reloadUser(UserInterface $user): void
    {
        $this->decorated->reloadUser($user);
    }

    public function updateUser(UserInterface $user, bool $andFlush = true): void
    {
        // Even if it is redundant,
        // we need to call this *BEFORE* calling the decorated object
        $this->updateCanonicalFields($user);

        $this->decorated->updateUser($user, $andFlush);
    }

    public function findUserByUsername(string $username): ?UserInterface
    {
        return $this->decorated->findUserByUsername($username);
    }

    public function findUserByEmail(string $email): ?UserInterface
    {
        return $this->decorated->findUserByEmail($email);
    }

    public function findUserByUsernameOrEmail(string $usernameOrEmail): ?UserInterface
    {
        return $this->decorated->findUserByUsernameOrEmail($usernameOrEmail);
    }

    public function findUserByConfirmationToken(string $token): ?UserInterface
    {
        return $this->decorated->findUserByConfirmationToken($token);
    }

    public function updatePassword(UserInterface $user): void
    {
        $this->decorated->updatePassword($user);
    }
}
