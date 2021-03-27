<?php

namespace AppBundle\Form\Model;

use libphonenumber\PhoneNumber;
use Nucleos\ProfileBundle\Form\Model\Registration as BaseRegistration;
use Nucleos\UserBundle\Model\UserInterface;
use Nucleos\UserBundle\Model\UserManagerInterface;

class ApiRegistration extends BaseRegistration
{
    /**
     * @var string|null
     */
    protected $givenName;

    /**
     * @var string|null
     */
    protected $familyName;

    /**
     * @var string|null
     */
    protected $fullName;

    /**
     * @var PhoneNumber|null
     */
    protected $telephone;

    /**
     * @return string|null
     */
    public function getGivenName()
    {
        return $this->givenName;
    }

    /**
     * @param string|null $givenName
     *
     * @return self
     */
    public function setGivenName($givenName)
    {
        $this->givenName = $givenName;

        return $this;
    }

    /**
     * @return string|null
     */
    public function getFamilyName()
    {
        return $this->familyName;
    }

    /**
     * @param string|null $familyName
     *
     * @return self
     */
    public function setFamilyName($familyName)
    {
        $this->familyName = $familyName;

        return $this;
    }

    /**
     * @return string|null
     */
    public function getFullName()
    {
        return $this->fullName;
    }

    /**
     * @param string|null $fullName
     *
     * @return self
     */
    public function setFullName($fullName)
    {
        $this->fullName = $fullName;

        return $this;
    }

    /**
     * @return PhoneNumber|null
     */
    public function getTelephone()
    {
        return $this->telephone;
    }

    /**
     * @param PhoneNumber|null $telephone
     *
     * @return self
     */
    public function setTelephone(?PhoneNumber $telephone)
    {
        $this->telephone = $telephone;

        return $this;
    }

    public function toUser(UserManagerInterface $manager): UserInterface
    {
        $user = parent::toUser($manager);

        $telephone = $this->getTelephone();

        if ($telephone) {
            $user->setTelephone($telephone);
        }

        $fullName = $this->getFullName();

        if (!empty($fullName)) {
            $user->getCustomer()->setFullName($fullName);
        } else {
            $user->getCustomer()->setFirstName($this->getGivenName());
            $user->getCustomer()->setLastName($this->getFamilyName());
        }

        return $user;
    }
}
