<?php

namespace AppBundle\Utils;

use AppBundle\Entity\TaskImage;
use Cocur\Slugify\SlugifyInterface;

class TaskImageNamer
{
    /**
     * @var \Cocur\Slugify\SlugifyInterface
     */
    protected $slugify;

    public function __construct(SlugifyInterface $slugify)
    {
        $this->slugify = $slugify;
    }

    public function getImageDownloadFileName(TaskImage $taskImage)
    {
        $task = $taskImage->getTask();
        $fileExtension = pathinfo($taskImage->getImageName(), PATHINFO_EXTENSION);

        $address = $task->getAddress();
        $addressName = !empty($address->getName()) ? $this->slugify->slugify($address->getName()) : '';

        return sprintf(
            "%d_%s_%s.%s",
            $taskImage->getId(),
            $addressName,
            $task->getCreatedAt()->format('Y-m-d'),
            $fileExtension
        );
    }
}
