<?php

namespace AppBundle\Entity\Model;

use AppBundle\Entity\Tag;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Util\ClassUtils;

trait TaggableTrait
{
    protected $tags;
    protected $tagManager;

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

    public function setTagManager($tagManager)
    {
        $this->tagManager = $tagManager;

        return $this;
    }
}
