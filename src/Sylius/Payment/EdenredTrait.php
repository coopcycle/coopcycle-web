<?php

namespace AppBundle\Sylius\Payment;

trait EdenredTrait
{
    public function setEdenredAuthorizationId($authorizationId)
    {
        $this->details = array_merge($this->details, [
            'edenred_authorization_id' => $authorizationId
        ]);
    }

    public function getEdenredAuthorizationId()
    {
        if (isset($this->details['edenred_authorization_id'])) {

            return $this->details['edenred_authorization_id'];
        }
    }

    public function setEdenredCaptureId($captureId)
    {
        $this->details = array_merge($this->details, [
            'edenred_capture_id' => $captureId
        ]);
    }

    public function getEdenredCaptureId()
    {
        if (isset($this->details['edenred_capture_id'])) {

            return $this->details['edenred_capture_id'];
        }
    }

    public function setEdenredCancelId($cancelId)
    {
        $this->details = array_merge($this->details, [
            'edenred_cancel_id' => $cancelId
        ]);
    }

    public function getEdenredCancelId()
    {
        if (isset($this->details['edenred_cancel_id'])) {

            return $this->details['edenred_cancel_id'];
        }
    }
}
