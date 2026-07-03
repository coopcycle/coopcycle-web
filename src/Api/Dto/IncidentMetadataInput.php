<?php

namespace AppBundle\Api\Dto;

use Symfony\Component\Serializer\Annotation\Groups;

class IncidentMetadataInput
{
    #[Groups(['incident'])]
    public array $metadata = [];
}
