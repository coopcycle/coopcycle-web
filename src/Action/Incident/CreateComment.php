<?php

namespace AppBundle\Action\Incident;

use AppBundle\Action\Base;
use AppBundle\Entity\Incident\Incident;
use AppBundle\Entity\Incident\IncidentEvent;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\Persistence\ObjectManager;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Security\Core\User\UserInterface;

class CreateComment extends Base
{
    public function __construct(private EntityManagerInterface $entityManager)
    {}

    public function __invoke(Incident $data, UserInterface $user, Request $request): Incident
    {
        $params = $this->parseRequest($request);

        $comment = trim($params->get("comment"));

        if (empty($comment)) {
            throw new \InvalidArgumentException("Comment cannot be empty");
        }

        $event = new IncidentEvent();
        $event->setType(IncidentEvent::TYPE_COMMENT);
        $event->setIncident($data);
        $event->setCreatedBy($user);
        $event->setMessage($comment);

        $data->addEvent($event);

        $this->entityManager->persist($data);
        $this->entityManager->flush();

        return $data;
    }

}
