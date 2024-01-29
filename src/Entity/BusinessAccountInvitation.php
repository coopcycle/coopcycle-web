<?php

namespace AppBundle\Entity;

class BusinessAccountInvitation
{
    private $id;
    private $businessAccount;
    private $invitation;

    public function getBusinessAccount()
    {
        return $this->businessAccount;
    }

    public function setBusinessAccount(?BusinessAccount $businessAccount)
    {
        $this->businessAccount = $businessAccount;

        return $this;
    }

    public function getInvitation()
    {
        return $this->invitation;
    }

    public function setInvitation(Invitation $invitation)
    {
        $this->invitation = $invitation;

        return $this;
    }

    public function isInvitationForManager()
    {
        if ($grants = $this->invitation->getGrants()) {
            if (isset($grants['roles'])) {
                return in_array('ROLE_BUSINESS_ACCOUNT', $grants['roles']);
            }
        }
        return false;
    }
}