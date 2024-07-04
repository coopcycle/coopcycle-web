<?php

namespace AppBundle\Entity;

class Invitation
{
    protected $code;

    protected $email;

    protected $user;

    protected $sentAt;

    protected array $grants = [];

    /**
     * @return mixed
     */
    public function getCode()
    {
        return $this->code;
    }

    /**
     * @param mixed $code
     *
     * @return self
     */
    public function setCode($code)
    {
        $this->code = $code;

        return $this;
    }

    /**
     * @return string
     */
    public function getEmail()
    {
        return $this->email;
    }

    /**
     * @param string $email
     *
     * @return self
     */
    public function setEmail($email)
    {
        $this->email = $email;

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
    public function getSentAt()
    {
        return $this->sentAt;
    }

    /**
     * @param mixed $sentAt
     *
     * @return self
     */
    public function setSentAt($sentAt)
    {
        $this->sentAt = $sentAt;

        return $this;
    }

    /**
     * @return array
     */
    public function getGrants()
    {
        return $this->grants;
    }

    /**
     * @param string $role
     */
    public function addRole(string $role)
    {
        $roles = $this->grants['roles'] ?? [];

        if (!in_array($role, $roles, true)) {
            $roles[] = $role;
        }

        $this->grants['roles'] = $roles;
    }

    /**
     * @param LocalBusiness $restaurant
     */
    public function addRestaurant(LocalBusiness $restaurant)
    {
        $restaurants = $this->grants['restaurants'] ?? [];

        if (!in_array($restaurant->getId(), $restaurants, true)) {
            $restaurants[] = $restaurant->getId();
        }

        $this->grants['restaurants'] = $restaurants;
    }

    /**
     * @param Store $store
     */
    public function addStore(Store $store)
    {
        $stores = $this->grants['stores'] ?? [];

        if (!in_array($store->getId(), $stores, true)) {
            $stores[] = $store->getId();
        }

        $this->grants['stores'] = $stores;
    }
}
