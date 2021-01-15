<?php

namespace AppBundle\Action\Task;

use AppBundle\Entity\TaskImage;
use AppBundle\Form\TaskImageType;
use ApiPlatform\Core\Bridge\Symfony\Validator\Exception\ValidationException;
use Doctrine\Persistence\ManagerRegistry;
use Symfony\Component\Form\FormFactoryInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Validator\Validator\ValidatorInterface;

/**
 * @see https://api-platform.com/docs/core/file-upload/
 */
class CreateImage
{
    protected $doctrine;
    protected $formFactory;
    protected $validator;

    public function __construct(
        ManagerRegistry $doctrine,
        FormFactoryInterface $formFactory,
        ValidatorInterface $validator)
    {
        $this->doctrine = $doctrine;
        $this->formFactory = $formFactory;
        $this->validator = $validator;
    }

    public function __invoke(Request $request)
    {
        $taskImage = new TaskImage();

        $form = $this->formFactory->create(TaskImageType::class, $taskImage);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {

            $em = $this->doctrine->getManager();
            $em->persist($taskImage);
            $em->flush();

            return $taskImage;
        }

        throw new ValidationException($this->validator->validate($taskImage));
    }
}
