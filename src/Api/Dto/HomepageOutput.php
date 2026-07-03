<?php

namespace AppBundle\Api\Dto;

use Symfony\Component\Serializer\Annotation\Groups;

final class HomepageOutput
{
    #[Groups(['ui.homepage'])]
    public bool $published;

    public function __construct(bool $published)
    {
        $this->published = $published;
    }
}
