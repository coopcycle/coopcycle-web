<?php

namespace AppBundle\Api\Dto;

use AppBundle\Entity\UI\HomepageBlock;
use Symfony\Component\Serializer\Annotation\Groups;

final class HomepageBlocks
{
    /**
     * @var HomepageBlock[]
     */
    #[Groups(['ui.homepage'])]
    public array $blocks = [];

}
