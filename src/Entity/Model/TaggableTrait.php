<?php

namespace AppBundle\Entity\Model;

use Doctrine\Common\Util\ClassUtils;
use Symfony\Component\Serializer\Annotation\Groups;
use Symfony\Component\Serializer\Annotation\SerializedName;

trait TaggableTrait
{
    protected array $tags = [];

    /**
     * {@inheritdoc}
     */
    public function getTaggableResourceClass()
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

    public function hasTag($tag): bool
    {
        return in_array($tag, $this->getTags());
    }

    /**
     * @SerializedName("tags")
     * @Groups({"task_create", "task_edit"})
     */
    public function setTags($tags)
    {
        $this->tags = is_array($tags) ? $tags : explode(' ', $tags);
        $this->tags = array_unique($this->tags);
    }

    public function addTags($tags)
    {
        $this->tags = array_merge(
            $this->getTags(),
            is_array($tags) ? $tags : explode(' ', $tags)
        );
        $this->tags = array_unique($this->tags);
    }

    public function addTag($tag): void
    {
        $this->addTags([$tag]);
    }

    public function removeTag($tag): void
    {
        $this->setTags(array_diff($this->getTags(), [$tag]));
    }
}
