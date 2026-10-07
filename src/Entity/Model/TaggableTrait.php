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
    #[Groups(['task', 'order', 'foodtech_order_minimal', 'delivery'])]
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
            // A callable is the lazy loader that TaggableSubscriber::postLoad
            // installs on every taggable entity as it is hydrated. It reads the
            // tags, it does not change them, so it must not touch updatedAt:
            // doing so made every entity dirty the moment it was loaded, and
            // the next flush rewrote all of them. Moving one task on the
            // dispatch board rewrote the courier's whole task list that way.
            $this->tagsCallable = $tags;

            return;
        }

        $this->tags = array_unique($this->normalizeTags($tags));
        $this->tagsCallable = null;

        $this->touchForTagsChange();
    }

    public function addTags(array|string $tags): void
    {
        $this->tags = array_merge(
            $this->getTags(),
            $this->normalizeTags($tags)
        );
        $this->tags = array_unique($this->tags);

        $this->touchForTagsChange();
    }

    /**
     * Tags may be given as a space-separated string, as a list of slugs,
     * or as a list of tags as serialized by TagManager::getTags()
     * (i.e ['name' => ..., 'slug' => ..., 'color' => ...]),
     * which is what clients send back when they re-submit a task they loaded.
     *
     * @return string[]
     */
    private function normalizeTags(array|string $tags): array
    {
        if (!is_array($tags)) {
            $tags = explode(' ', $tags);
        }

        $slugs = [];
        foreach ($tags as $tag) {
            if (is_array($tag)) {
                $tag = $tag['slug'] ?? null;
            }
            if (is_string($tag) && '' !== $tag) {
                $slugs[] = $tag;
            }
        }

        return array_values($slugs);
    }

    /**
     * 'tags' is not a mapped field, so an entity whose tags changed looks clean
     * to Doctrine and TaggableSubscriber would never see it in the flush. Touch
     * updatedAt to schedule it.
     *
     * Only actual changes may call this. It used to run on load as well, via
     * setTags() being handed the lazy loader from TaggableSubscriber::postLoad,
     * which made every taggable entity dirty the moment it was hydrated.
     */
    private function touchForTagsChange(): void
    {
        if (property_exists($this, 'updatedAt')) {
            $this->updatedAt = new \DateTime();
        }
    }
}
