<?php

namespace AppBundle\Api\Dto;

use Symfony\Component\Serializer\Annotation\Groups;

final class EdenredCredentialsInput
{
    /**
     * @var string
     * @Groups({"update_edenred_credentials"})
     */
    public string $accessToken;

    /**
     * @var string
     * @Groups({"update_edenred_credentials"})
     */
    public string $refreshToken;
}
