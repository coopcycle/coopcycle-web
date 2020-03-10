<?php

namespace AppBundle\Entity;

use ApiPlatform\Core\Annotation\ApiResource;
use AppBundle\Api\Dto\CreateRemotePushTokenRequest;

/**
 * @ApiResource(
 *   collectionOperations={
 *     "post"={
 *       "path"="/me/remote_push_tokens",
 *       "input"=CreateRemotePushTokenRequest::class,
 *       "swagger_context"={
 *         "summary": "Creates a RemotePushToken resource for iOS."
 *       }
 *     },
 *   },
 *   itemOperations={
 *     "get"={"method"="GET"},
 *     "delete"={
 *       "method"="DELETE",
 *       "path"="/me/remote_push_tokens/{id}"
 *     }
 *   }
 * )
 */
class RemotePushToken
{
    protected $id;

    protected $user;

    protected $platform;

    protected $token;

    protected $createdAt;

    protected $updatedAt;

    public function getId()
    {
        return $this->id;
    }

    public function getUser()
    {
        return $this->user;
    }

    public function setUser($user)
    {
        $this->user = $user;

        return $this;
    }

    public function getPlatform()
    {
        return $this->platform;
    }

    public function setPlatform($platform)
    {
        $this->platform = $platform;

        return $this;
    }

    public function getToken()
    {
        return $this->token;
    }

    public function setToken($token)
    {
        $this->token = $token;

        return $this;
    }
}
