<?php

namespace AppBundle\Action\Task;

use ApiPlatform\Core\Api\IriConverterInterface;
use ApiPlatform\Core\Bridge\Symfony\Validator\Exception\ValidationException;
use AppBundle\Entity\Task;
use AppBundle\Entity\TaskImage;
use AppBundle\Form\TaskImageType;
use Doctrine\Persistence\ManagerRegistry;
use Doctrine\Persistence\ObjectManager;
use Symfony\Component\Form\FormFactoryInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Validator\Validator\ValidatorInterface;

/**
 * @see https://api-platform.com/docs/core/file-upload/
 */
class CreateImage
{

    private ObjectManager $entityManager;
    public function __construct(
        ManagerRegistry $doctrine,
        protected FormFactoryInterface $formFactory,
        protected IriConverterInterface $iriConverter,
        protected ValidatorInterface $validator)
    {
        $this->entityManager = $doctrine->getManager();
    }

    public function __invoke(Request $request)
    {
        $taskImage = new TaskImage();

        // Ugly hack to improve task validation speed
        if ($request->headers->has('X-Attach-To')) {
            $tasks = array_map(function(string $task): Task {
                return $this->iriConverter->getItemFromIri($task);
            }, explode(';', $request->headers->get('X-Attach-To')));
        }

        $form = $this->formFactory->create(TaskImageType::class, $taskImage);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $this->entityManager->persist($taskImage);

            if (isset($tasks)) {
                $this->cloneAndAttach($tasks, $taskImage);
            }

            $this->entityManager->flush();
            return $taskImage;
        }

        throw new ValidationException($this->validator->validate($taskImage));
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
