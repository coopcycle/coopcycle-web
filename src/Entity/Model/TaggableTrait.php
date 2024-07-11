<?php

namespace AppBundle\Entity\Model;

use Doctrine\Common\Util\ClassUtils;
use Symfony\Component\Serializer\Annotation\Groups;
use Symfony\Component\Serializer\Annotation\SerializedName;

trait TaggableTrait
{
    protected array $tags = [];

    public function getTaggableResourceClass(): string
    {
        return ClassUtils::getClass($this);
    }

    /**
     * @SerializedName("tags")
     * @Groups({"task", "order", "order_minimal"})
     */
    public function getTags(): array
    {
        return $this->tags;
    }

    public function hasTag(string $tag): bool
    {
        return in_array($tag, $this->getTags());
    }

    /**
     * @SerializedName("tags")
     * @Groups({"task_create", "task_edit"})
     */
    public function setTags(array|string $tags): void
    {
        $this->tags = is_array($tags) ? $tags : explode(' ', $tags);
        $this->tags = array_unique($this->tags);
    }

    public function addTags(array|string $tags): void
    {
        $this->tags = array_merge(
            $this->getTags(),
            is_array($tags) ? $tags : explode(' ', $tags)
        );
        $this->tags = array_unique($this->tags);
    }

    public function addTag(string $tag): void
    {
        $this->addTags([$tag]);
    }

    public function removeTag(string $tag): void
    {
        $this->setTags(array_diff($this->getTags(), [$tag]));
    }
}
