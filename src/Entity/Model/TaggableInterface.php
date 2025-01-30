<?php

namespace AppBundle\Entity\Model;

interface TaggableInterface
{
    public function getTaggableResourceClass(): string;

    /**
     * @return string[]
     */
    public function getTags(): array;

    /**
     * Set all tags for Taggable (override existing tags) 
     * 
     * @param string|string[] $tags
     */
    public function setTags(array|string $tags): void;

    /**
     * @param string|string[] $tags
    */
    public function addTags(array|string $tags): void;

}
