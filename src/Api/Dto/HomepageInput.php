<?php

namespace AppBundle\Api\Dto;

use Symfony\Component\Serializer\Annotation\Groups;

final class HomepageInput
{
    #[Groups(['ui.homepage'])]
    public bool $published;
}
