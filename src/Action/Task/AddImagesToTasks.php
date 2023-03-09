<?php

namespace AppBundle\Action\Task;

use ApiPlatform\Core\Api\IriConverterInterface;
use AppBundle\Entity\TaskImage;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;

class AddImagesToTasks
{
    private $iriConverter;
    private $entityManager;

    public function __construct(
        IriConverterInterface $iriConverter,
        EntityManagerInterface $entityManager)
    {
        $this->iriConverter = $iriConverter;
        $this->entityManager = $entityManager;
    }

    public function __invoke(Request $request)
    {
        $payload = [];

        $content = $request->getContent();
        if (!empty($content)) {
            $payload = json_decode($content, true);
        }

        if (!isset($payload['tasks']) || !isset($payload['images'])) {
            throw new BadRequestHttpException('Mandatory parameters are missing');
        }

        $tasks = $payload["tasks"];
        $images = $payload["images"];

        $imagesObj = [];
        foreach($images as $image) {
            $imagesObj[] = $this->iriConverter->getItemFromIri($image);
        }

        $tasksResults = [];
        foreach($tasks as $task) {
            $taskObj = $this->iriConverter->getItemFromIri($task);

            if (null === $imagesObj[0]->getTask()) {
                // existing task_images have not a task associated yet
                $tasksResults[] = $taskObj->addImages($imagesObj);
            } else {
                // persist new task_images to relation them with this task
                $newImages = $this->createAndPersistTaskImages($imagesObj);
                $tasksResults[] = $taskObj->addImages($newImages);
            }
        }

        $this->entityManager->flush();

        return $tasksResults;
    }

    private function createAndPersistTaskImages($images)
    {
        $result = [];

        foreach($images as $image) {
            $newImage = new TaskImage();
            $newImage->setImageName($image->getImageName());

            $this->entityManager->persist($newImage);
            $this->entityManager->flush();

            $result[] = $newImage;
        }

        return $result;
    }
}
