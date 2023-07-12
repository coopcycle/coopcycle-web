<?php

namespace AppBundle\MessageHandler;

use AppBundle\Entity\Base\GeoCoordinates;
use AppBundle\Entity\TrackingPosition;
use AppBundle\Message\UpdateLocation;
use Doctrine\ORM\EntityManagerInterface;
use Nucleos\UserBundle\Model\UserManager as UserManagerInterface;
use Symfony\Component\Messenger\Handler\MessageHandlerInterface;

class UpdateLocationHandler implements MessageHandlerInterface
{
    private $entityManager;
    private $userManager;

    public function __construct(
        EntityManagerInterface $entityManager,
        UserManagerInterface $userManager)
    {
        $this->entityManager = $entityManager;
        $this->userManager = $userManager;
    }

    public function __invoke(UpdateLocation $message)
    {
        if (!$user = $this->userManager->findUserByUsername($message->getUsername())) {
            return;
        }

        foreach ($message->getLocations() as $location) {

            $date = new \DateTime();
            $date->setTimestamp((int) $location['timestamp']);

            $trackingPosition = new TrackingPosition();
            $trackingPosition->setCourier($user);
            $trackingPosition->setCoordinates(
                new GeoCoordinates($location['latitude'], $location['longitude'])
            );
            $trackingPosition->setDate($date);

            $this->entityManager->persist($trackingPosition);
        }

        $this->entityManager->flush();
    }
}
