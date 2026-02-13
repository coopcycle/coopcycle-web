<?php

namespace AppBundle\Entity\UI;

use Doctrine\Common\Collections\ArrayCollection;
use Gedmo\Timestampable\Traits\Timestampable;
use Symfony\Component\Serializer\Annotation\Groups;

class Block
{
    private int $id;
    private int $position;
    public Homepage $homepage;

    #[Groups(['ui.homepage'])]
    public string $type;

    #[Groups(['ui.homepage'])]
    public array $data = [];
}
