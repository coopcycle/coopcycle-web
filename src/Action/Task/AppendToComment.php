<?php

namespace AppBundle\Action\Task;

use AppBundle\Entity\Task;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Security\Core\Security;

class AppendToComment extends Base
{
    public function __construct(
        private EntityManagerInterface $entityManager,
        private Security $security)
    {}

    public function __invoke(Task $data, Request $request): Task
    {
        $note = $this->getNote($request);
        if (empty($note)) {
            return $data;
        }

        $data->appendToComments(
            sprintf("%s: %s",
                $this->security->getUser()->getUserIdentifier(),
                $note
            )
        );

        $this->entityManager->persist($data);
        $this->entityManager->flush();

        return $data;
    }
}
