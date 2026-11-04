<?php

namespace Tests\AppBundle\Integration\Rdc\DTO;

use AppBundle\Integration\Rdc\DTO\RdcApiServiceRequest;
use PHPUnit\Framework\TestCase;

class RdcApiServiceRequestTest extends TestCase
{
    public function testGetPickupTimeSlotAnchorsInstantToUtc(): void
    {
        $request = RdcApiServiceRequest::parse([
            'startLocation' => [
                'requestedStartTimeRange' => [
                    'earliestDateTime' => '2024-06-01T08:00:00Z',
                    'latestDateTime' => '2024-06-01T10:00:00Z',
                ],
            ],
        ]);

        $slot = $request->getPickupTimeSlot();

        $this->assertNotNull($slot->start);
        $this->assertNotNull($slot->end);
        $this->assertSame('UTC', $slot->start->getTimezone()->getName());
        $this->assertSame('UTC', $slot->end->getTimezone()->getName());
        $this->assertSame('2024-06-01T08:00:00+00:00', $slot->start->format('c'));
        $this->assertSame('2024-06-01T10:00:00+00:00', $slot->end->format('c'));
    }

    public function testGetDropoffTimeSlotAnchorsInstantToUtc(): void
    {
        $request = RdcApiServiceRequest::parse([
            'endLocation' => [
                'requestedEndTimeRange' => [
                    'earliestDateTime' => '2024-06-01T14:00:00Z',
                    'latestDateTime' => '2024-06-01T16:30:00Z',
                ],
            ],
        ]);

        $slot = $request->getDropoffTimeSlot();

        $this->assertNotNull($slot->start);
        $this->assertNotNull($slot->end);
        $this->assertSame('UTC', $slot->start->getTimezone()->getName());
        $this->assertSame('2024-06-01T14:00:00+00:00', $slot->start->format('c'));
        $this->assertSame('2024-06-01T16:30:00+00:00', $slot->end->format('c'));
    }

    public function testTimeSlotReturnsNullWhenRangeMissing(): void
    {
        $request = RdcApiServiceRequest::parse([]);

        $pickup = $request->getPickupTimeSlot();
        $dropoff = $request->getDropoffTimeSlot();

        $this->assertNull($pickup->start);
        $this->assertNull($pickup->end);
        $this->assertNull($dropoff->start);
        $this->assertNull($dropoff->end);
    }

    public function testAddressesCarryTheLocationName(): void
    {
        $request = RdcApiServiceRequest::parse([
            'startLocation' => ['location' => ['locationName' => 'C.Market Grands Hommes', 'address' => []]],
            'endLocation' => ['location' => ['address' => []]],
        ]);

        $this->assertSame('C.Market Grands Hommes', $request->getPickupAddress()->name);
        $this->assertSame('', $request->getDropoffAddress()->name);
    }

    public function testContactNamesComeFromPersons(): void
    {
        $request = RdcApiServiceRequest::parse([
            'contacts' => [
                [
                    'role' => 'SHIPPER',
                    'email' => 'contact@pharmacie-exemple.fr',
                    'persons' => [
                        ['familyName' => 'Martin', 'givenName' => 'Claire', 'telephone' => '0600000001'],
                    ],
                ],
                ['role' => 'RECIPIENT', 'familyName' => 'Doe', 'telephone' => '0600000002'],
            ],
        ]);

        $shipper = $request->getShipperContact();
        $this->assertSame('Claire Martin', $shipper->name);
        // The person's phone is used when the contact has none
        $this->assertSame('0600000001', $shipper->phone);
        $this->assertSame('contact@pharmacie-exemple.fr', $shipper->email);

        // Contact-level familyName is kept as a fallback
        $this->assertSame('Doe', $request->getRecipientContact()->name);
    }

    public function testShipperContactIsEmptyWhenAbsent(): void
    {
        $request = RdcApiServiceRequest::parse(['contacts' => [['role' => 'RECIPIENT', 'familyName' => 'Doe']]]);

        $this->assertSame('', $request->getShipperContact()->name);
    }

    public function testLocationSpecialInstructions(): void
    {
        $request = RdcApiServiceRequest::parse([
            'startLocation' => ['location' => ['address' => []]],
            'endLocation' => ['location' => [
                'address' => [],
                'specialInstructions' => [['description' => 'Livraison par la cour']],
            ]],
        ]);

        $this->assertSame([], $request->getPickupSpecialInstructions());
        $this->assertSame([['description' => 'Livraison par la cour']], $request->getDropoffSpecialInstructions());
    }
}
