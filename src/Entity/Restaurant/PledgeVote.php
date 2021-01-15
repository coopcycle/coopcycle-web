<?php

namespace AppBundle\Entity\Restaurant;

class PledgeVote {


// pledge (the current pledge)
// user (the user that is voting)
// votedAt (the date when the pledge was voted)

    protected $id;

    protected $pledge;

    protected $user;

    protected $votedAt;

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
    public function getPledge()
    {
        return $this->pledge;
    }

    /**
     * @param mixed $pledge
     *
     * @return self
     */
    public function setPledge($pledge)
    {
        $this->pledge = $pledge;

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
    public function getVotedAt()
    {
        return $this->votedAt;
    }

    /**
     * @param mixed $votedAt
     *
     * @return self
     */
    public function setVotedAt($votedAt)
    {
        $this->votedAt = $votedAt;

        return $this;
    }
}
