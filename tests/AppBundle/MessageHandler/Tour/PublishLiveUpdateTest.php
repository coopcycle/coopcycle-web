<?php

namespace Tests\AppBundle\MessageHandler\Tour;

use AppBundle\MessageHandler\Tour\PublishLiveUpdate;
use AppBundle\Domain\Tour\Event\TourCreated;
use AppBundle\Domain\Tour\Event\TourUpdated;
use AppBundle\Service\LiveUpdates;
use AppBundle\Service\LiveUpdatesSuspension;
use PHPUnit\Framework\TestCase;
use Prophecy\Argument;
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
            $this->liveUpdates->reveal(),
            new LiveUpdatesSuspension()
        );
    }

    public function testNothingIsSentWhileSuspended()
    {
        $suspension = new LiveUpdatesSuspension();
        $publishLiveUpdate = new PublishLiveUpdate($this->liveUpdates->reveal(), $suspension);

        $event = $this->prophesize(TourUpdated::class);

        $suspension->during(fn () => $publishLiveUpdate($event->reveal()));

        $this->liveUpdates->toDispatchers(Argument::cetera())->shouldNotHaveBeenCalled();
    }

    public function testTourEvents()
    {
        $tourEvents = [
            TourCreated::class,
            TourUpdated::class,
        ];

        foreach ($tourEvents as $eventClass) {
            $event = $this->prophesize($eventClass);

            $this->liveUpdates->toDispatchers(
                $event->reveal()
            )->shouldBeCalledOnce();

            ($this->publishLiveUpdate)($event->reveal());

            // Reset mock expectations for next iteration
            $this->liveUpdates = $this->prophesize(LiveUpdates::class);
            $this->publishLiveUpdate = new PublishLiveUpdate(
                $this->liveUpdates->reveal(),
                new LiveUpdatesSuspension()
            );
        }
    }

}
