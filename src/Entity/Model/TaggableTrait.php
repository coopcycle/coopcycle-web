<?php

namespace AppBundle\Entity\Model;

use Doctrine\Common\Util\ClassUtils;
use Symfony\Component\Serializer\Annotation\Groups;
use Symfony\Component\Serializer\Annotation\SerializedName;
use Monolog\Logger;
use Monolog\Handler\StreamHandler;
use Doctrine\Persistence\ManagerRegistry;

trait TaggableTrait
{
    protected $tags;

    /**
     * {@inheritdoc}
     */
    public function getTaggableResourceClass()
    {
        return ClassUtils::getClass($this);
    }

    /**
     * @SerializedName("tags")
     * @Groups({"task", "customer", "update_profile"})
     */
    public function getTags()
    {
        if (null === $this->tags) {
            $this->tags = [];
        }
        $log = new Logger('getTags');
        $log->pushHandler(new StreamHandler('php://stdout', Logger::WARNING)); // <<< uses a stream
        $log->warning('getTags');
        $log->warning(print_r($this->tags, true));
        return $this->tags;
    }

    /**
     * @SerializedName("tags")
     * @Groups({"task_create", "task_edit", "update_profile"})
     */
    public function setTags($tags)
    {
        $log = new Logger('setTags');
        $log->pushHandler(new StreamHandler('php://stdout', Logger::WARNING)); // <<< uses a stream
        $log->warning('setTags');
        $log->warning(print_r($tags, true));
        $this->tags = is_array($tags) ? $tags : explode(' ', $tags);
        $log->warning(print_r($this->tags, true));
        $this->tags = array_unique($this->tags);
        $log->warning(print_r($this->tags, true));
    }

    public function addTags($tags)
    {
        $this->tags = array_merge(
            $this->getTags(),
            is_array($tags) ? $tags : explode(' ', $tags)
        );
        $this->tags = array_unique($this->tags);
    }
}
