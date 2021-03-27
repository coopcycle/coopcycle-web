<?php

namespace AppBundle\Entity\Restaurant;

use AppBundle\Entity\Restaurant\PledgeVote;
use AppBundle\Entity\LocalBusiness;
use Doctrine\Common\Collections\ArrayCollection;
use Nucleos\UserBundle\Model\UserInterface;

class Pledge {

// name (the name of the restaurant)
// address (the address of the restaurant)
// user (the user that created the pledge)
// state (the state of the pledge. Can be new, accepted, rejected)
// createdAt (the date when the pledge was created)

	protected $id;

	protected $name;

	protected $address;

	protected $user;

	protected $state;

	protected $createdAt;

    protected $votes;


    public function __construct()
    {
        $this->votes = new ArrayCollection();
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

    /**
     * @return mixed
     */
    public function getAddress()
    {
        return $this->address;
    }

    /**
     * @param mixed $address
     *
     * @return self
     */
    public function setAddress($address)
    {
        $this->address = $address;

        return $this;
    }

    /**
     * @return mixed
     */
    public function getUser()
    {
        return $this->user;
    }

    /**
     * @param mixed $user
     *
     * @return self
     */
    public function setUser($user)
    {
        $this->user = $user;

        return $this;
    }

    /**
     * @return mixed
     */
    public function getState()
    {
        return $this->state;
    }

    /**
     * @param mixed $state
     *
     * @return self
     */
    public function setState($state)
    {
        $this->state = $state;

        return $this;
    }

    /**
     * @return mixed
     */
    public function getCreatedAt()
    {
        return $this->createdAt;
    }

    /**
     * @param mixed $createdAt
     *
     * @return self
     */
    public function setCreatedAt($createdAt)
    {
        $this->createdAt = $createdAt;

        return $this;
    }

    /**
     * @return mixed
     */
    public function getVotes()
    {
        return $this->votes;
    }

    /**
     * @param mixed $votes
     *
     * @return self
     */
    public function setVotes($votes)
    {
        $this->votes = $votes;

        return $this;
    }

    public function addVote(UserInterface $user)
    {
        if (!$this->hasVoted($user)) {
            $vote = new PledgeVote();
            $vote->setUser($user);
            $vote->setPledge($this);
            $this->getVotes()->add($vote);
        }
    }

    public function hasVoted(UserInterface $user): bool
    {
        foreach ($this->getVotes() as $vote) {
            if ($vote->getUser() === $user) {
                return true;
            }
        }
        return false;
    }

    public function accept(): LocalBusiness
    {
        if ($this->getState() !== 'new') {
            throw new \Exception('Unvalid pledge state, it should be set to new');
        }

        $restaurant = new LocalBusiness();
        $restaurant->setName($this->getName());
        $restaurant->setAddress($this->getAddress());
        $restaurant->setState(LocalBusiness::STATE_PLEDGE);
        $restaurant->setPledge($this);
        $restaurant->setEnabled(true);

        $this->setState('accepted');

        return $restaurant;
    }
}
