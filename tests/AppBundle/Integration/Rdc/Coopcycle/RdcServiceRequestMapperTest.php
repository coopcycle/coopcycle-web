<?php

namespace Tests\AppBundle\Integration\Rdc\Coopcycle;

use AppBundle\Entity\Task;
use AppBundle\Integration\Rdc\Coopcycle\RdcServiceRequestMapper;
use AppBundle\Integration\Rdc\DTO\TimeSlot;
use AppBundle\Service\DeliveryManager;
use AppBundle\Service\Geocoder;
use Carbon\Carbon;
use libphonenumber\PhoneNumberUtil;
use PHPUnit\Framework\TestCase;
use Prophecy\PhpUnit\ProphecyTrait;
use Psr\Log\NullLogger;
use ReflectionMethod;

class RdcServiceRequestMapperTest extends TestCase
{
    use ProphecyTrait;

    private const INSTANCE_TZ = 'Europe/Paris';

    private RdcServiceRequestMapper $mapper;

    protected function setUp(): void
    {
        $previousTz = date_default_timezone_get();
        date_default_timezone_set(self::INSTANCE_TZ);

        $this->mapper = new RdcServiceRequestMapper(
            $this->prophesize(DeliveryManager::class)->reveal(),
            $this->prophesize(Geocoder::class)->reveal(),
            $this->prophesize(PhoneNumberUtil::class)->reveal(),
            new NullLogger(),
        );

        // Restore on tearDown
        register_shutdown_function(static fn () => date_default_timezone_set($previousTz));
    }

    public function testMapTimeSlotReturnsUtcAnchoredDateTimes(): void
    {
        $slot = new TimeSlot(
            start: Carbon::parse('2024-06-01T08:00:00Z')->utc()->toDateTimeImmutable(),
            end: Carbon::parse('2024-06-01T10:00:00Z')->utc()->toDateTimeImmutable(),
        );

        $result = $this->mapper->mapTimeSlot($slot);

        $this->assertSame('UTC', $result['start']->getTimezone()->getName());
        $this->assertSame('UTC', $result['end']->getTimezone()->getName());
        $this->assertSame('2024-06-01T08:00:00+00:00', $result['start']->format('c'));
        $this->assertSame('2024-06-01T10:00:00+00:00', $result['end']->format('c'));
    }

    public function testSetTaskTimeRangeConvertsUtcInstantToLocalTimezone(): void
    {
        // 2024-06-01T08:00:00Z = 10:00:00 in Europe/Paris (CEST, UTC+2)
        $slot = new TimeSlot(
            start: Carbon::parse('2024-06-01T08:00:00Z')->utc()->toDateTimeImmutable(),
            end: Carbon::parse('2024-06-01T10:00:00Z')->utc()->toDateTimeImmutable(),
        );

        $task = new Task();

        $setTaskTimeRange = new ReflectionMethod($this->mapper, 'setTaskTimeRange');
        $setTaskTimeRange->invoke($this->mapper, $task, $slot);

        $this->assertSame(self::INSTANCE_TZ, $task->getDoneAfter()->getTimezone()->getName());
        $this->assertSame(self::INSTANCE_TZ, $task->getDoneBefore()->getTimezone()->getName());
        $this->assertSame('2024-06-01T10:00:00+02:00', $task->getDoneAfter()->format('c'));
        $this->assertSame('2024-06-01T12:00:00+02:00', $task->getDoneBefore()->format('c'));
    }

    public function testSetTaskTimeRangeSkipsNullBounds(): void
    {
        $slot = new TimeSlot(start: null, end: null);

        $task = new Task();

        $setTaskTimeRange = new ReflectionMethod($this->mapper, 'setTaskTimeRange');
        $setTaskTimeRange->invoke($this->mapper, $task, $slot);

        $this->assertNull($task->getDoneAfter());
        $this->assertNull($task->getDoneBefore());
    }

    public function testBuildCommentsFromSpecialInstructionsReturnsNullForNullInput(): void
    {
        $this->assertNull($this->invokeBuildComments(null));
    }

    public function testBuildCommentsFromSpecialInstructionsReturnsNullForEmptyArray(): void
    {
        $this->assertNull($this->invokeBuildComments([]));
    }

    public function testBuildCommentsFromSpecialInstructionsFormatsKnownCodes(): void
    {
        $comments = $this->invokeBuildComments([
            ['instructionCode' => 'COMMENT', 'description' => 'Porte garage bois bleue'],
            ['instructionCode' => 'DIGICODE', 'description' => '10345A'],
            ['instructionCode' => 'FLOOR', 'description' => '1'],
        ]);

        $this->assertSame(
            "💬 COMMENT: Porte garage bois bleue\n🔢 DIGICODE: 10345A\n🏢 FLOOR: 1",
            $comments
        );
    }

    public function testBuildCommentsFromSpecialInstructionsUsesPinForUnknownCode(): void
    {
        $comments = $this->invokeBuildComments([
            ['instructionCode' => 'SOMETHING_ELSE', 'description' => 'Whatever'],
        ]);

        $this->assertSame('📌 SOMETHING_ELSE: Whatever', $comments);
    }

    public function testBuildCommentsFromSpecialInstructionsSkipsEntriesMissingCodeOrDescription(): void
    {
        $comments = $this->invokeBuildComments([
            ['instructionCode' => 'COMMENT', 'description' => 'Keep me'],
            ['instructionCode' => 'COMMENT'],
            ['description' => 'No code here'],
            ['instructionCode' => 'DIGICODE', 'description' => ''],
            ['instructionCode' => 'FLOOR', 'description' => '3'],
        ]);

        $this->assertSame("💬 COMMENT: Keep me\n🏢 FLOOR: 3", $comments);
    }

    public function testBuildCommentsFromSpecialInstructionsReturnsNullWhenAllEntriesAreInvalid(): void
    {
        $comments = $this->invokeBuildComments([
            ['instructionCode' => 'COMMENT'],
            ['description' => 'No code'],
            ['instructionCode' => 'DIGICODE', 'description' => ''],
        ]);

        $this->assertNull($comments);
    }

    private function invokeBuildComments(?array $specialInstructions): ?string
    {
        $method = new ReflectionMethod($this->mapper, 'buildCommentsFromSpecialInstructions');
        return $method->invoke($this->mapper, $specialInstructions);
    }
}
