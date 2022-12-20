<?php

namespace AppBundle\Api\Dto;

use Symfony\Component\Serializer\Annotation\Groups;

final class BioDeliverInput
{
    /**
     * @Groups({"task_edit"})
     */
    public $address;

    /**
     * @Groups({"task_edit"})
     */
    public $comments;
}

