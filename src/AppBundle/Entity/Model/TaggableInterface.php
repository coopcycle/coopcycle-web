<?php

namespace AppBundle\Entity\Model;

interface TaggableInterface
{
    /**
     * @return string
     */
    public function getTaggableResourceClass();

    /**
     * @return Doctrine\Common\Collections\Collection
     */
    public function getTags();

}
