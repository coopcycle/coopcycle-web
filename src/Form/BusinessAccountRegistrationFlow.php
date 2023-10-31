<?php

namespace AppBundle\Form;

use Craue\FormFlowBundle\Form\FormFlow;
use Craue\FormFlowBundle\Form\FormFlowEvents;
use Craue\FormFlowBundle\Event\PostBindSavedDataEvent;

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
			],
		];
    }

    public static function getSubscribedEvents() : array {
		return [
			FormFlowEvents::POST_BIND_SAVED_DATA => 'onPostBindSavedData',
		];
	}

	public function onPostBindSavedData(PostBindSavedDataEvent $event) {
		if ($event->getFlow() !== $this) {
			return;
		}

        if ($event->getStepNumber() === 1) {
            $form = $event->getForm();
            $child = $form->get('username');
            $config = $child->getConfig();
            $options = $config->getOptions();
            $options['help'] = 'form.registration.username.help';
            $form->add('username', get_class($config->getType()->getInnerType()), $options);
        }

		if ($event->getStepNumber() === 2) {
			$formData = $event->getFormData();
            $formData->businessAccount = $formData->getBusinessAccount();
		}
	}
}
