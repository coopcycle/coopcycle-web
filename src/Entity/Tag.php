<?php

namespace AppBundle\Entity;

use Symfony\Component\Serializer\Annotation\Groups;
use ApiPlatform\Core\Annotation\ApiResource;
use Symfony\Component\Validator\Constraints as Assert;

/**
 * @ApiResource(
 *   attributes={
 *     "normalization_context"={"groups"={"tag"}}
 *   },
 *   collectionOperations={
 *     "get"={
 *       "method"="GET",
 *       "access_control"="is_granted('ROLE_DISPATCHER') or is_granted('ROLE_COURIER')",
 *       "pagination_enabled"=false,
 *     },
 *  })
 */
class Tag
{
    const ADDRESS_NEED_REVIEW_TAG = 'review-needed';

    protected $id;

    /**
     * @Groups({"task", "tag"})
     */
    protected $name;

    /**
     * @Groups({"task", "tag"})
     */
    private $slug;

    /**
     * @Groups({"task", "tag"})
     * @Assert\NotBlank()
     */
    private $color;

    private $createdAt;

    private $updatedAt;

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
