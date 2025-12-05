<?php

namespace AppBundle\Action\Task;

use ApiPlatform\Api\IriConverterInterface;
use AppBundle\Entity\Task;
use AppBundle\Entity\TaskImage;
use AppBundle\Form\TaskImageType;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\HttpFoundation\Request;
use ApiPlatform\Validator\ValidatorInterface;

/**
 * @see https://api-platform.com/docs/core/file-upload/
 */
class CreateImage
{
    public function __construct(
        private EntityManagerInterface $entityManager,
        protected IriConverterInterface $iriConverter,
        private ValidatorInterface $validator)
    {}

    public function __invoke(Request $request)
    {
        $uploadedFile = $request->files->get('file');

        $taskImage = new TaskImage();

        $taskImage->setFile($uploadedFile);

        $this->validator->validate($taskImage, ['groups' => ['task_image_create']]);

        // Ugly hack to improve task validation speed
        if ($request->headers->has('X-Attach-To')) {

            $tasks = array_map(
                fn(string $task): Task => $this->iriConverter->getResourceFromIri($task),
                explode(';', $request->headers->get('X-Attach-To'))
            );

            $this->cloneAndAttach($tasks, $taskImage);
        }

        return $taskImage;
    }

    private function cloneAndAttach(array $tasks, TaskImage $taskImage): void
    {
        $this->entityManager->persist($taskImage);

        $first = array_shift($tasks);
        $taskImage->setTask($first);

        foreach ($tasks as $task) {

            $otherTaskImage = new TaskImage();
            $otherTaskImage->setImageName($taskImage->getImageName());
            $otherTaskImage->setTask($task);

            $this->entityManager->persist($otherTaskImage);
        }
    }
}
