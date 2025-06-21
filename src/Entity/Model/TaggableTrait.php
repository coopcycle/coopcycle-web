<?php

namespace AppBundle\Entity\Model;

use Doctrine\Common\Util\ClassUtils;
use Symfony\Component\Serializer\Annotation\Groups;
use Symfony\Component\Serializer\Annotation\SerializedName;

trait TaggableTrait
{
    /**
     * @var string[]
     */
    protected array $tags = [];

    protected $tagsCallable = null;

    public function getTaggableResourceClass(): string
    {
        return ClassUtils::getClass($this);
    }

    #[SerializedName('tags')]
    #[Groups(['task', 'order', 'order_minimal', 'delivery'])]
    public function getTags(): array
    {
        if (is_callable($this->tagsCallable)) {
            $this->tags = call_user_func($this->tagsCallable);
            $this->tagsCallable = null;
        }

        return $this->tags;
    }

    #[SerializedName('tags')]
    #[Groups(['task_create', 'task_edit'])]
    public function setTags(array|string|callable $tags): void
    {
        if (is_callable($tags)) {
            $this->tagsCallable = $tags;
        } else {
            $this->tags = is_array($tags) ? $tags : explode(' ', $tags);
            $this->tags = array_unique($this->tags);
        }
    }

    public function addTags(array|string $tags): void
    {
        $this->tags = array_merge(
            $this->getTags(),
            is_array($tags) ? $tags : explode(' ', $tags)
        );
        $this->tags = array_unique($this->tags);
    }
}
