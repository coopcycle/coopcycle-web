<?php

namespace AppBundle\Form;

use AppBundle\Entity\BusinessAccount;
use AppBundle\Entity\User;
use Symfony\Component\Validator\Constraints as Assert;

class BusinessAccountRegistration
{
    /**
	 * @Assert\Valid
	 */
	public $user;

    /**
	 * @Assert\Valid
	 */
	public $businessAccount;

	/**
	 * @Assert\Valid
	 */
	public $invitationLink;

    public function __construct(User $user, BusinessAccount $businessAccount, $invitationLink = null) {
		$this->user = $user;
		$this->businessAccount = $businessAccount;
		$this->invitationLink = $invitationLink;
	}
}