<?php

namespace AppBundle\Entity\Model;

use Doctrine\Common\Collections\Collection;

interface TaggableInterface
{
    /**
     * @return string
     */
    public function getTaggableResourceClass();

    /**
     * @return Collection
     */
    public function getTags();

    /**
     * @param mixed $tags
     */
    public function setTags($tags);
}
