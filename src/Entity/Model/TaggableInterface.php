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
    public function getTags(): array;

    public function hasTag($tag): bool;

    /**
     * @param string[]|string $tags
     */
    public function setTags($tags);

    public function addTags($tags);

    public function addTag($tag): void;

    public function removeTag($tag): void;
}
