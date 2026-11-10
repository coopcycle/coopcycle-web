<?php

declare(strict_types=1);

namespace Tests\AppBundle\Integration\Rdc;

/**
 * The same workflow for a home delivery: the courier picks a parcel up at the
 * requestor's shop and drops it at its customer's. Such a requestor sends no
 * SHIPPER contact, contacts with a phone only, and each address as one line
 * with no postal code or city apart.
 */
class RdcHomeDeliveryWorkflowTest extends RdcWorkflowTest
{
    protected const LABEL = '637900000';

    protected function serviceRequestFixture(): string
    {
        return 'service_request_home_delivery.json';
    }

    public function testTheAddressLinesAndLocationNamesMakeTheTasks(): void
    {
        $delivery = $this->receiveServiceRequest();

        $pickupAddress = $delivery->getPickup()->getAddress();
        $this->assertSame('Boutique Exemple', $pickupAddress->getName());
        $this->assertSame('Place des Grands Hommes, Bordeaux, France', $pickupAddress->getStreetAddress());

        // The customer's name is only on the location
        $dropoffAddress = $delivery->getDropoff()->getAddress();
        $this->assertSame("Rue d'Aviau 1 33000 Bordeaux France", $dropoffAddress->getStreetAddress());
        $this->assertSame('JANE DOE', $dropoffAddress->getContactName());
        $this->assertSame('600000002', (string) $dropoffAddress->getTelephone()?->getNationalNumber());
    }
}
