<?php

namespace AppBundle\Api\Resource;

use ApiPlatform\Metadata\Post;
use ApiPlatform\Metadata\Get;
use ApiPlatform\Metadata\ApiResource;
use ApiPlatform\Metadata\ApiProperty;
use ApiPlatform\Metadata\ApiFilter;
use ApiPlatform\Action\NotFoundAction;
use AppBundle\Api\State\UrbantzWebhookProvider;
use AppBundle\Api\State\UrbantzWebhookProcessor;
use Symfony\Component\Serializer\Annotation\Groups;
use Symfony\Component\Serializer\Annotation\SerializedName;

#[ApiResource(
    operations: [
        new Get(
            controller: NotFoundAction::class,
            output: false,
            read: false
        ),
        new Post(
            uriTemplate: '/urbantz/webhook/{id}',
            status: 200,
            openapiContext: ['summary' => 'Receives a webhook from Urbantz.'],
            normalizationContext: ['groups' => ['urbantz_output']],
            denormalizationContext: ['groups' => ['urbantz_input']],
            security: 'is_granted(\'ROLE_API_KEY\')',
            provider: UrbantzWebhookProvider::class,
            processor: UrbantzWebhookProcessor::class
        )
    ]
)]
final class UrbantzWebhook
{
    const TASKS_ANNOUNCED = 'tasks_announced';
    const TASK_CHANGED    = 'task_changed';
    const TASK_UNASSOCIATED = 'task_unassociated';

    /**
     * @var string
     */
    #[ApiProperty(identifier: true)]
    public $id;

    #[Groups(['urbantz_input'])]
    public $tasks = [];

    #[Groups(['urbantz_output'])]
    public $deliveries = [];

    #[Groups(['urbantz_input'])]
    public $extTrackId;

    public function __construct(string $id = null)
    {
        $this->id = $id;
    }

    public static function isValidEvent(string $eventName)
    {
        return in_array($eventName, [
            self::TASKS_ANNOUNCED,
            self::TASK_CHANGED,
            self::TASK_UNASSOCIATED,
        ]);
    }
}
