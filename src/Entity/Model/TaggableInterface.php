<?php

namespace AppBundle\Entity\Model;

interface TaggableInterface
{
    public function getTaggableResourceClass(): string;

    /**
     * @return string[]
     */
    public function getTags(): array;

    public function hasTag(string $tag): bool;

    /**
     * @param string|string[] $tags
     */
    public function setTags(array|string $tags): void;

    public function addTags(array|string $tags): void;

    public function addTag(string $tag): void;

    public function removeTag(string $tag): void;
}
