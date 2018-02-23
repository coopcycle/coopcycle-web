<?php

namespace AppBundle\Entity;

use Doctrine\ORM\Mapping as ORM;

/**
 * @ORM\Entity
 * @ORM\Table(uniqueConstraints={
 *   @ORM\UniqueConstraint(name="tagging_unique", columns={"resource_class", "resource_id", "tag_id"})}
 * )
 */
class Tagging
{
    /**
     * @ORM\Id
     * @ORM\Column(type="integer")
     * @ORM\GeneratedValue(strategy="IDENTITY")
     */
    protected $id;

    /**
     * @ORM\Column(type="string")
     */
    protected $resourceClass;

    /**
     * @ORM\Column(type="integer")
     */
    protected $resourceId;

    /**
     * @ORM\ManyToOne(targetEntity="Tag")
     * @ORM\JoinColumn(nullable=false)
     */
    protected $tag;

    public function getTag()
    {
        return $this->tag;
    }

    public function setTag(Tag $tag)
    {
        $this->tag = $tag;

        return $this;
    }

    public function getResourceClass()
    {
        return $this->resourceClass;
    }

    public function setResourceClass($resourceClass)
    {
        $this->resourceClass = $resourceClass;

        return $this;
    }

    public function getResourceId()
    {
        return $this->resourceId;
    }

    public function setResourceId($resourceId)
    {
        $this->resourceId = $resourceId;

        return $this;
    }
}
