<?php

namespace AppBundle\Api\Dto;

use Symfony\Component\Serializer\Annotation\Groups;

final class EdenredCredentialsInput
{
    #[Groups(['update_edenred_credentials'])]
    public string $accessToken;

    #[Groups(['update_edenred_credentials'])]
    public string $refreshToken;
}
