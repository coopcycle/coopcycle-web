<?php

namespace Tests\AppBundle\Entity\Listener;

use AppBundle\Entity\Address;
use AppBundle\Entity\Listener\AddressListener;
use AppBundle\Message\CalculateRoute;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\Event\PreUpdateEventArgs;
use PHPUnit\Framework\TestCase;
use Prophecy\Argument;
use Prophecy\PhpUnit\ProphecyTrait;
use Symfony\Component\Messenger\Envelope;
use Symfony\Component\Messenger\MessageBusInterface;

class AddressListenerTest extends TestCase
{
    use ProphecyTrait;

    protected function setUp(): void
    {
        $this->entityManager = $this->prophesize(EntityManagerInterface::class);
        $this->messageBus = $this->prophesize(MessageBusInterface::class);

        $this->messageBus
            ->dispatch(Argument::type(CalculateRoute::class))
            ->will(function ($args) {
                return new Envelope($args[0]);
            });

        $this->listener = new AddressListener(
            $this->messageBus->reveal()
        );
    }

    public function testGeoHasChangedIgnoresSRID()
    {
        $address = new Address();

        $changeSet = [
            'geo' => [
                'SRID=4326;POINT(2.309128 48.872815)',
                'POINT(2.309128 48.872815)'
            ]
        ];

        $event = new PreUpdateEventArgs($address, $this->entityManager->reveal(), $changeSet);

        $this->listener->preUpdate($address, $event);

        $this
            ->messageBus
            ->dispatch(Argument::type(CalculateRoute::class))
            ->shouldNotHaveBeenCalled();
    }

    public function testGeoHasChanged()
    {
        $address = $this->prophesize(Address::class);
        $address->getId()->willReturn(1);

        $changeSet = [
            'geo' => [
                'SRID=4326;POINT(2.309127 48.872815)',
                'POINT(2.309128 48.872815)'
            ]
        ];

        $event = new PreUpdateEventArgs($address->reveal(), $this->entityManager->reveal(), $changeSet);

        $this->listener->preUpdate($address->reveal(), $event);

        $this
            ->messageBus
            ->dispatch(new CalculateRoute(1))
            ->shouldHaveBeenCalled();
    }
}
