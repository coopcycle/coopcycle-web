<?php

namespace AppBundle\Entity;

class Tagging
{
    protected $id;

    protected $resourceClass;

    protected $resourceId;

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
