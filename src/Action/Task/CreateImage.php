<?php

namespace AppBundle\Action\Task;

use ApiPlatform\Api\IriConverterInterface;
use AppBundle\Entity\Task;
use AppBundle\Entity\TaskImage;
use AppBundle\Form\TaskImageType;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\HttpFoundation\Request;

/**
 * @see https://api-platform.com/docs/core/file-upload/
 */
class CreateImage
{
    public function __construct(
        private EntityManagerInterface $entityManager,
        protected IriConverterInterface $iriConverter)
    {}

    public function __invoke(Request $request)
    {
        $uploadedFile = $request->files->get('file');

        $taskImage = new TaskImage();

        // Ugly hack to improve task validation speed
        if ($request->headers->has('X-Attach-To')) {
            $tasks = array_map(function(string $task): Task {
                return $this->iriConverter->getResourceFromIri($task);
            }, explode(';', $request->headers->get('X-Attach-To')));
        }

        $taskImage->setFile($uploadedFile);

        if (isset($tasks)) {
            $this->cloneAndAttach($tasks, $taskImage);
        }

        return $taskImage;
    }

    private function cloneAndAttach(&$tasks, TaskImage &$taskImage): void {
        $first = array_shift($tasks);
        $first->addImages([$taskImage]);
        $this->entityManager->persist($first);
        foreach ($tasks as &$task) {
            $_taskImage = new TaskImage();
            $_taskImage->setImageName($taskImage->getImageName());
            $task->addImages([$_taskImage]);
            $this->entityManager->persist($task);
        }

    }
}
