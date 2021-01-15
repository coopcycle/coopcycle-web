<?php

namespace AppBundle\Entity;

use ApiPlatform\Core\Annotation\ApiProperty;
use ApiPlatform\Core\Annotation\ApiResource;
use AppBundle\Api\Dto\CreateRemotePushTokenRequest;
use AppBundle\Action\DeleteToken as DeleteTokenController;

/**
 * @ApiResource(
 *   collectionOperations={
 *     "post"={
 *       "path"="/me/remote_push_tokens",
 *       "input"=CreateRemotePushTokenRequest::class
 *     },
 *   },
 *   itemOperations={
 *     "get"={"method"="GET"},
 *     "delete_by_token"={
 *       "method"="DELETE",
 *       "path"="/me/remote_push_tokens/{token}",
 *       "read"=false,
 *       "write"=false,
 *       "controller"=DeleteTokenController::class,
 *       "requirements"={"token"=".+"}
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
