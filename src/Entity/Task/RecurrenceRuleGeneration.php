<?php

declare(strict_types=1);

namespace AppBundle\Entity\Task;

use ApiPlatform\Metadata\ApiResource;
use ApiPlatform\Metadata\Get;
use ApiPlatform\Metadata\Post;
use AppBundle\Action\Task\GenerateOrders;
use Gedmo\Timestampable\Traits\Timestampable;
use Symfony\Component\Serializer\Annotation\Groups;
use Symfony\Component\Serializer\Annotation\SerializedName;

/**
 * The state of one run of the order generation, for one date.
 *
 * There is at most one row per date - the unique index on `date` is what keeps
 * two dispatchers from generating the same day twice. The row outlives the run,
 * so a failure is still readable tomorrow morning, which a log line is not.
 */
#[ApiResource(
    shortName: 'RecurrenceRuleGeneration',
    operations: [
        // Declared first on purpose: API Platform builds the "@id" of a run
        // from the first item GET it finds, and this is the one that points at
        // the run itself rather than at the endpoint that started it.
        new Get(
            requirements: ['id' => '[0-9]+'],
            security: "is_granted('ROLE_DISPATCHER')",
        ),
        // Kept on this resource rather than on RecurrenceRule, so the response
        // is serialized with this class' groups. A POST because asking for a
        // date queues work.
        new Post(
            uriTemplate: '/recurrence_rules/generate_orders',
            controller: GenerateOrders::class,
            read: false,
            deserialize: false,
            write: false,
            status: 201,
            security: "is_granted('ROLE_DISPATCHER')",
        ),
        // The same thing over GET, as the endpoint answered before the
        // generation moved to a worker - clients out there still call it that
        // way, and a verb change would break them.
        new Get(
            uriTemplate: '/recurrence_rules/generate_orders',
            controller: GenerateOrders::class,
            read: false,
            deserialize: false,
            write: false,
            status: 201,
            security: "is_granted('ROLE_DISPATCHER')",
        ),
    ],
    normalizationContext: ['groups' => ['recurrence_rule_generation']],
    security: "is_granted('ROLE_DISPATCHER')"
)]
class RecurrenceRuleGeneration
{
    use Timestampable;

    public const STATUS_PENDING = 'pending';
    public const STATUS_STARTED = 'started';
    public const STATUS_COMPLETED = 'completed';
    public const STATUS_FAILED = 'failed';

    private $id;

    #[Groups(['recurrence_rule_generation'])]
    private string $status = self::STATUS_PENDING;

    #[Groups(['recurrence_rule_generation'])]
    private int $succeeded = 0;

    #[Groups(['recurrence_rule_generation'])]
    private int $failed = 0;

    /**
     * How many times the worker picked the run up. Messenger retries a failed
     * message, so without this a row reading "2 failed" is ambiguous between one
     * bad run and four of them.
     */
    #[Groups(['recurrence_rule_generation'])]
    private int $attempts = 0;

    /**
     * @var array<int, array{recurrence_rule: int|null, message: string}>
     */
    #[Groups(['recurrence_rule_generation'])]
    private array $errors = [];

    private ?\DateTime $startedAt = null;

    private ?\DateTime $finishedAt = null;

    public function __construct(private \DateTimeInterface $date)
    {
    }

    public function getId()
    {
        return $this->id;
    }

    public function getDate(): \DateTimeInterface
    {
        return $this->date;
    }

    #[SerializedName('date')]
    #[Groups(['recurrence_rule_generation'])]
    public function getDateAsString(): string
    {
        return $this->date->format('Y-m-d');
    }

    public function getStatus(): string
    {
        return $this->status;
    }

    public function getSucceeded(): int
    {
        return $this->succeeded;
    }

    public function getFailed(): int
    {
        return $this->failed;
    }

    public function getAttempts(): int
    {
        return $this->attempts;
    }

    public function getErrors(): array
    {
        return $this->errors;
    }

    public function getStartedAt(): ?\DateTime
    {
        return $this->startedAt;
    }

    public function getFinishedAt(): ?\DateTime
    {
        return $this->finishedAt;
    }

    /**
     * Queued or being worked on. This is the lock: while a row is in one of
     * these, asking for the date again hands back the run in progress instead
     * of starting a second one.
     *
     * @return string[]
     */
    public static function runningStatuses(): array
    {
        return [self::STATUS_PENDING, self::STATUS_STARTED];
    }

    public function start(): void
    {
        $this->status = self::STATUS_STARTED;
        $this->attempts++;
        $this->succeeded = 0;
        $this->failed = 0;
        $this->errors = [];
        $this->startedAt = new \DateTime();
        $this->finishedAt = null;
    }

    public function succeed(): void
    {
        $this->succeeded++;
    }

    public function fail(?int $recurrenceRuleId, string $message): void
    {
        $this->failed++;
        $this->errors[] = [
            'recurrence_rule' => $recurrenceRuleId,
            'message' => $message,
        ];
    }

    public function finish(): void
    {
        $this->status = $this->failed > 0 ? self::STATUS_FAILED : self::STATUS_COMPLETED;
        $this->finishedAt = new \DateTime();
    }

    /**
     * The run died on something other than a single rule - nothing more will be
     * generated for the date until it is asked for again.
     */
    public function abort(string $message): void
    {
        $this->fail(null, $message);

        $this->status = self::STATUS_FAILED;
        $this->finishedAt = new \DateTime();
    }
}
