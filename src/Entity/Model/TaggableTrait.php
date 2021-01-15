<?php

namespace AppBundle\Entity\Model;

use AppBundle\Entity\Tag;
use AppBundle\Service\TagManager;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Util\ClassUtils;
use Symfony\Component\Serializer\Annotation\Groups;
use Symfony\Component\Serializer\Annotation\SerializedName;

trait TaggableTrait
{
    protected $tags;
    protected $tagManager;
    protected $tagsFast;

    /**
     * {@inheritdoc}
     */
    public function getTaggableResourceClass()
    {
        return ClassUtils::getClass($this);
    }

    /**
     * {@inheritdoc}
     */
    public function getTags()
    {
        if ($this->tags === null) {
            $this->tags = new ArrayCollection();
            if ($this->tagManager) {
                foreach ($this->tagManager->getTags($this) as $tag) {
                    $this->tags->add($tag);
                }
            }
        }

        return $this->tags;
    }

    /**
     * @SerializedName("tags")
     * @Groups({"task"})
     */
    public function getTagsFast()
    {
        if ($this->tagsFast === null) {
            $this->tagsFast = new ArrayCollection();
            if ($this->tagManager) {
                foreach ($this->tagManager->getTags($this, [ 'cache' => true ]) as $tag) {
                    $this->tagsFast->add($tag);
                }
            }
        }

        return $this->tagsFast;
    }

    public function setTags($tags)
    {
        $newTags = null;
        if (is_object($tags) && $tags instanceof ArrayCollection) {
            $newTags = $tags;
        } elseif (is_array($tags)) {
            $newTags = new ArrayCollection($tags);
        } else {
            throw new \InvalidArgumentException('Parameter should be of type array or ArrayCollection');
        }

        if (null !== $this->getId() && null !== $this->tagManager) {
            $originalTags = $this->getTags();
            foreach ($originalTags as $originalTag) {
                if (!$newTags->contains($originalTag)) {
                    $this->tagManager->removeTag($this, $originalTag);
                }
            }
            foreach ($newTags as $newTag) {
                if (!$originalTags->contains($newTag)) {
                    $this->tagManager->addTag($this, $newTag);
                }
            }
        }

        $this->tags = $newTags;
    }

    public function setTagManager(TagManager $tagManager)
    {
        $this->tagManager = $tagManager;

        return $this;
    }
}
