<?php

namespace AppBundle\Entity\Model;

interface TaggableInterface
{
    /**
     * @return string
     */
    public function getTaggableResourceClass();

    /**
     * @return string[]
     */
    public function getTags();

    /**
     * @param string[]|string $tags
     */
    public function setTags($tags);
}
