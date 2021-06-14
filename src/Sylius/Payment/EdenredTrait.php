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

    public function setAmountBreakdown(int $edenredAmount, int $cardAmount)
    {
        $this->details = array_merge($this->details, [
            'amount_breakdown' => [
                'edenred' => $edenredAmount,
                'card'    => $cardAmount,
            ]
        ]);
    }

    public function clearAmountBreakdown()
    {
        if (isset($this->details['amount_breakdown'])) {
            unset($this->details['amount_breakdown']);
        }
    }

    public function getAmountBreakdown()
    {
        if (isset($this->details['amount_breakdown'])) {
            return $this->details['amount_breakdown'];
        }

        return [
            'card' => $this->getAmount(),
        ];
    }

    public function getAmountForMethod($method)
    {
        $breakdown = $this->getAmountBreakdown();

        return $breakdown[strtolower($method)];
    }

    public function isEdenredWithCard()
    {
        $method = $this->getMethod();

        return null !== $method && $method->getCode() === 'EDENRED+CARD';
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

    public function getRefundableAmountForMethod($method, $amount = null)
    {
        switch ($method) {
            case 'CARD':
                return min($this->getAmountForMethod('CARD'), $amount ?? $this->getAmount());

            case 'EDENRED':
                return min($this->getAmountForMethod('CARD'), ($amount ?? $this->getAmount()) - $this->getRefundableAmountForMethod('CARD'));
        }

        return 0;
    }
}
