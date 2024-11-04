<?php

namespace AppBundle\Action\Task;

use AppBundle\Entity\Task;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Security\Core\Security;
use Symfony\Component\Security\Core\User\UserInterface;

class AppendToComment extends Base {

    private UserInterface $user;

    public function __construct(
        private EntityManagerInterface $entityManager,
        Security $security,
    )
    {
        $this->user = $security->getUser();
    }

    public function __invoke(Task $data, Request $request): Task
    {
        $note = $this->getNote($request);
        if (empty($note)) {
            return $data;
        }

        $data->appendToComments(
            sprintf("%s: %s",
                $this->user->getUserIdentifier(),
                $note
            )
        );

        $this->entityManager->persist($data);
        $this->entityManager->flush();

        return $data;
    }
}
