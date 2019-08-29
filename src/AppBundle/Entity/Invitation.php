<?php
// src/AppBundle/Entity/Invitation.php

namespace AppBundle\Entity;

class Invitation
{
    protected $code;

    protected $email;

    /**
     * When sending invitation be sure to set this value to `true`
     *
     * It can prevent invitations from being sent twice
     */
    protected $sent = false;

    public function setCode($code)
    {
        return $this->code = $code;
    }

    public function getCode()
    {
        return $this->code;
    }

    public function getEmail()
    {
        return $this->email;
    }

    public function setEmail($email)
    {
        $this->email = $email;
    }

    public function isSent()
    {
        return $this->sent;
    }

    public function send()
    {
        $this->sent = true;
    }
}
