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

    public function __construct(User $user, BusinessAccount $businessAccount) {
		$this->user = $user;
		$this->businessAccount = $businessAccount;
	}
}