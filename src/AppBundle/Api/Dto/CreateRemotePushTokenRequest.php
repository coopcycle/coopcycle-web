<?php

namespace AppBundle\Api\Dto;

use ApiPlatform\Core\Annotation\ApiResource;
use Symfony\Component\Validator\Constraints as Assert;

/**
 * @ApiResource(
 *   collectionOperations={
 *     "post"={
 *       "path"="/me/remote_push_tokens",
 *       "swagger_context"={
 *         "summary": "Creates a RemotePushToken resource for iOS."
 *       }
 *     },
 *   },
 *   itemOperations={},
 * )
 */
final class CreateRemotePushTokenRequest
{
    /**
     * @Assert\NotBlank
     */
    public $platform;

    /**
     * @Assert\NotBlank
     */
    public $token;
}
