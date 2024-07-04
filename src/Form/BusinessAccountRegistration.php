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
	public $code;

    public function __construct(User $user, BusinessAccount $businessAccount, $code = null) {
		$this->user = $user;
		$this->businessAccount = $businessAccount;
		$this->code = $code;
	}
}