<?php

namespace AppBundle\Utils;

use AppBundle\Entity\TaskImage;
use Cocur\Slugify\SlugifyInterface;

class TaskImageNamer
{
    public function getImageDownloadFileName(TaskImage $taskImage, SlugifyInterface $slugify)
    {
        $task = $taskImage->getTask();
        $fileExtension = pathinfo($taskImage->getImageName(), PATHINFO_EXTENSION);

        /** @var \AppBundle\Entity\Address $address */
        $address = $task->getAddress();
        $addressName = $address && $address->getName() ? $slugify->slugify($address->getName()) : "";

        $fileName = sprintf(
            "%d_%s_%s.%s",
            $taskImage->getId(),
            $addressName,
            $task->getCreatedAt()->format('Y-m-d'),
            $fileExtension
        );

        return $fileName;
    }
}
