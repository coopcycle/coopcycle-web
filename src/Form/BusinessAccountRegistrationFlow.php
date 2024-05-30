<?php

namespace AppBundle\Form;

use Craue\FormFlowBundle\Form\FormFlow;
use Craue\FormFlowBundle\Form\FormFlowInterface;
use Symfony\Component\Security\Core\Security;

class BusinessAccountRegistrationFlow extends FormFlow
{
	public function __construct(private Security $security)
	{}

    protected function loadStepsConfig()
    {
        $formType = BusinessAccountRegistrationForm::class;

        return [
			[
				'label' => 'registration.step.personal',
				'form_type' => $formType,
				'skip' => function($estimatedCurrentStepNumber, FormFlowInterface $flow) {

					$data = $flow->getFormData();
					$user = $data->user;
					if (null !== $user->getId() && $user->isEnabled() && $user === $this->security->getUser()) {
						return true;
					}

					return false;
				},
			],
			[
				'label' => 'registration.step.company',
				'form_type' => $formType,
			],
		];
    }

}
