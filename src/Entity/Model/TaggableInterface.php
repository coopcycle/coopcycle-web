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
     * @param string|string[] $tags
     */
    public function setTags(array|string $tags): void;

}
