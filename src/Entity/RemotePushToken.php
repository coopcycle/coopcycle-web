<?php

namespace AppBundle\Entity;

use ApiPlatform\Metadata\Post;
use ApiPlatform\Metadata\Delete;
use ApiPlatform\Metadata\Get;
use ApiPlatform\Metadata\ApiResource;
use ApiPlatform\Metadata\ApiProperty;
use ApiPlatform\Metadata\ApiFilter;
use AppBundle\Api\Dto\CreateRemotePushTokenRequest;
use AppBundle\Api\State\RemotePushTokenProcessor;
use AppBundle\Action\DeleteToken;

#[ApiResource(
    operations: [
        new Get(),
        new Delete(
            uriTemplate: '/me/remote_push_tokens/{token}',
            requirements: ['token' => '.+'],
            controller: DeleteToken::class,
            read: false,
            write: false
        ),
        new Post(
            uriTemplate: '/me/remote_push_tokens',
            input: CreateRemotePushTokenRequest::class,
            processor: RemotePushTokenProcessor::class
        )
    ]
)]
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
