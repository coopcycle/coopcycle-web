<?php

namespace AppBundle\Form;

use Craue\FormFlowBundle\Form\FormFlow;

class BusinessAccountRegistrationFlow extends FormFlow
{
    protected function loadStepsConfig()
    {
        $formType = BusinessAccountRegistrationForm::class;

        return [
			[
				'label' => 'registration.step.personal',
				'form_type' => $formType,
			],
			[
				'label' => 'registration.step.company',
				'form_type' => $formType,
			],
			[
				'label' => 'registration.step.invitation',
				'form_type' => $formType,
			],
		];
    }

}
