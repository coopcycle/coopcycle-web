<?php

namespace AppBundle\Api\Resource;

use ApiPlatform\Core\Action\NotFoundAction;
use ApiPlatform\Core\Annotation\ApiProperty;
use ApiPlatform\Core\Annotation\ApiResource;
use AppBundle\Action\Urbantz\ReceiveWebhook as ReceiveWebhookController;
use AppBundle\Api\Dto\UrbantzOrderInput;
use Symfony\Component\Serializer\Annotation\Groups;
use Symfony\Component\Serializer\Annotation\SerializedName;

/**
 * @ApiResource(
 *   collectionOperations={},
 *   itemOperations={
 *     "get": {
 *       "method"="GET",
 *       "controller"=NotFoundAction::class,
 *       "read"=false,
 *       "output"=false
 *     },
 *     "receive_webhook"={
 *       "method"="POST",
 *       "path"="/urbantz/webhook/{id}",
 *       "input"=UrbantzOrderInput::class,
 *       "controller"=ReceiveWebhookController::class,
 *       "denormalization_context"={"groups"={"urbantz_input"}},
 *       "normalization_context"={"groups"={"urbantz_output"}},
 *       "security"="is_granted('ROLE_API_KEY')",
 *       "status"=200,
 *       "openapi_context"={
 *         "summary"="Receives a webhook from Urbantz.",
 *       }
 *     }
 *   }
 * )
 */
final class UrbantzWebhook
{
    const TASKS_ANNOUNCED = 'tasks_announced';
    const TASK_CHANGED    = 'task_changed';

    /**
     * @var string
     *
     * @ApiProperty(identifier=true)
     */
    public $id;

    /**
     * @Groups({"urbantz_input"})
     */
    public $tasks = [];

    /**
     * @Groups({"urbantz_output"})
     */
    public $deliveries = [];

    public $hub;

    public function __construct(string $id = null)
    {
        $this->id = $id;
    }

    public static function isValidEvent(string $eventName)
    {
        return in_array($eventName, [
            self::TASKS_ANNOUNCED,
            self::TASK_CHANGED,
        ]);
    }
}
