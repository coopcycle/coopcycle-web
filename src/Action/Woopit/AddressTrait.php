<?php

namespace AppBundle\Action\Woopit;

trait AddressTrait
{
    protected function getAddressDescription(array $addressData): string
    {
        $streetDescription = '';

        if (isset($addressData['addressLine2'])) {
            $streetDescription = $addressData['addressLine2'];
        }

        if (isset($addressData['floor'])) {
            $streetDescription .= ' | Floor ' . $addressData['floor'];
        }

        if (isset($addressData['doorCode'])) {
            $streetDescription .= ' | Door code ' . $addressData['doorCode'];
        }

        if (isset($addressData['comment'])) {
            $streetDescription .= ' | ' . $addressData['comment'];
        }

        return $streetDescription;
    }
}
