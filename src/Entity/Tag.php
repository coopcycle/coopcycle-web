<?php

namespace AppBundle\Entity;

use ApiPlatform\Metadata\GetCollection;
use ApiPlatform\Metadata\Delete;
use ApiPlatform\Metadata\Patch;
use ApiPlatform\Metadata\Put;
use ApiPlatform\Metadata\Get;
use ApiPlatform\Metadata\ApiResource;
use ApiPlatform\Metadata\ApiProperty;
use ApiPlatform\Metadata\ApiFilter;
use Symfony\Component\Serializer\Annotation\Groups;
use Gedmo\Timestampable\Traits\Timestampable;
use Symfony\Component\Validator\Constraints as Assert;

#[ApiResource(operations: [new Get(), new Put(), new Patch(), new Delete(), new GetCollection(security: 'is_granted(\'ROLE_DISPATCHER\') or is_granted(\'ROLE_COURIER\')', paginationEnabled: false)], normalizationContext: ['groups' => ['tag']])]
class Tag
{
    use Timestampable;

    const ADDRESS_NEED_REVIEW_TAG = 'review-needed';

    protected $id;

    #[Groups(['task', 'tag'])]
    protected $name;

    #[Groups(['task', 'tag'])]
    private $slug;

    #[Groups(['task', 'tag'])]
    #[Assert\NotBlank]
    private $color;

    public function __construct($slug = null)
    {
        if (null !== $slug) {
            $this->slug = $slug;
        }
    }

    public function getId()
    {
        return $this->id;
    }

    public function getName()
    {
        return $this->name;
    }

    public function setName($name)
    {
        $this->name = $name;

        return $this;
    }

    public function getSlug()
    {
        return $this->slug;
    }

    public function getColor()
    {
        return $this->color;
    }

    public function setColor($color)
    {
        $this->color = $color;

        return $this;
    }
}
