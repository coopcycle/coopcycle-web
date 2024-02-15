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

	/**
	 * @Assert\Valid
	 */
	public $code;

    public function __construct(User $user, BusinessAccount $businessAccount, $invitationLink = null, $code = null) {
		$this->user = $user;
		$this->businessAccount = $businessAccount;
		$this->invitationLink = $invitationLink;
		$this->code = $code;
	}
}