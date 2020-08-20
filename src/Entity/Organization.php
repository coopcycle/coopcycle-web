<?php
declare(strict_types=1);

namespace AppBundle\Entity;


use Doctrine\Common\Collections\ArrayCollection;

class Organization
{
    private $id;
    private $name;
    private $users;

    public function __construct()
    {
        $this->users = new ArrayCollection();
    }
    /**
     * @return mixed
     */
    public function getUsers()
    {
        return $this->users;
    }

    public function addUser(ApiUser $user): void
    {
        if ($this->users->contains($user)) {
            return;
        }
        $user->addOrganization($this);
        $this->users->add($user);
    }
    /**
     * @return mixed
     */
    public function getId()
    {
        return $this->id;
    }

    /**
     * @return mixed
     */
    public function getName()
    {
        return $this->name;
    }

    /**
     * @param mixed $name
     *
     * @return self
     */
    public function setName($name)
    {
        $this->name = $name;

        return $this;
    }
}
