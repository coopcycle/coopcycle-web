<?php

namespace AppBundle\Action\Task;

use ApiPlatform\Core\Api\IriConverterInterface;
use ApiPlatform\Core\Bridge\Symfony\Validator\Exception\ValidationException;
use AppBundle\Entity\Task;
use AppBundle\Entity\TaskImage;
use AppBundle\Form\TaskImageType;
use Doctrine\Persistence\ManagerRegistry;
use Symfony\Component\Form\FormFactoryInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Validator\Validator\ValidatorInterface;

/**
 * @see https://api-platform.com/docs/core/file-upload/
 */
class CreateImage
{

    public function __construct(
        protected ManagerRegistry $doctrine,
        protected FormFactoryInterface $formFactory,
        protected IriConverterInterface $iriConverter,
        protected ValidatorInterface $validator)
    { }

    public function __invoke(Request $request)
    {
        $taskImage = new TaskImage();

        // Ugly hack to improve task validation speed
        if ($request->headers->has('X-Attach-To')) {
            /** @var Task $task */
            $task = $this->iriConverter->getItemFromIri($request->headers->get('X-Attach-To'));
        }

        $form = $this->formFactory->create(TaskImageType::class, $taskImage);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {

            $em = $this->doctrine->getManager();
            $em->persist($taskImage);
            $em->flush();

            if (isset($task)) {
                $task->addImages([$taskImage]);
                $em->persist($task);
                $em->flush();
            }

            return $taskImage;
        }

        throw new ValidationException($this->validator->validate($taskImage));
    }
}
