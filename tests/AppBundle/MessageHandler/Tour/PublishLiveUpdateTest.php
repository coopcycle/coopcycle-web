<?php

namespace Tests\AppBundle\MessageHandler\Tour;

use AppBundle\MessageHandler\Tour\PublishLiveUpdate;
use AppBundle\Domain\Tour\Event\TourCreated;
use AppBundle\Domain\Tour\Event\TourUpdated;
use AppBundle\Service\LiveUpdates;
use PHPUnit\Framework\TestCase;
use Prophecy\PhpUnit\ProphecyTrait;
use AppBundle\Entity\User;

class PublishLiveUpdateTest extends TestCase
{
    use ProphecyTrait;

    private PublishLiveUpdate $publishLiveUpdate;
    private $liveUpdates;

    public function setUp(): void
    {
        $this->liveUpdates = $this->prophesize(LiveUpdates::class);
        $this->publishLiveUpdate = new PublishLiveUpdate(
            $this->liveUpdates->reveal()
        );
    }

    public function testTourEvents()
    {
        $tourEvents = [
            TourCreated::class,
            TourUpdated::class,
        ];

        foreach ($tourEvents as $eventClass) {
            $event = $this->prophesize($eventClass);

            $this->liveUpdates->toAdmins(
                $event->reveal()
            )->shouldBeCalledOnce();

            ($this->publishLiveUpdate)($event->reveal());

            // Reset mock expectations for next iteration
            $this->liveUpdates = $this->prophesize(LiveUpdates::class);
            $this->publishLiveUpdate = new PublishLiveUpdate(
                $this->liveUpdates->reveal()
            );
        }
    }

}
