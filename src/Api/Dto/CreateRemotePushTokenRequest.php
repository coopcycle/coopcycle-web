<?php

namespace AppBundle\Api\Dto;

use Symfony\Component\Validator\Constraints as Assert;

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
