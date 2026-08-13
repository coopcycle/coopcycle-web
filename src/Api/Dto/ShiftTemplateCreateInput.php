<?php

namespace AppBundle\Api\Dto;

use Symfony\Component\Serializer\Annotation\Groups;
use Symfony\Component\Validator\Constraints as Assert;

final class ShiftTemplateCreateInput
{
    #[Groups(['shift_template_create'])]
    #[Assert\NotBlank]
    #[Assert\Length(max: 255)]
    public ?string $name = null;

    /**
     * Any date within the week to snapshot; the week aligns to the Monday.
     */
    #[Groups(['shift_template_create'])]
    #[Assert\NotBlank]
    #[Assert\Date]
    public ?string $week = null;
}
