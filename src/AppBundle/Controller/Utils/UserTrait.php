<?php

namespace AppBundle\Controller\Utils;

use AppBundle\Entity\TrackingPosition;
use FOS\UserBundle\Model\UserInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;

trait UserTrait
{
    protected function userTracking(UserInterface $user, \DateTime $date, $layout = 'profile')
    {
        $qb = $this->getDoctrine()
            ->getRepository(TrackingPosition::class)->createQueryBuilder('tp');
        $qb
            ->andWhere('tp.courier = :user')
            ->andWhere('DATE(tp.date) >= :date')
            ->setParameter('user', $user)
            ->setParameter('date', $date)
            ->orderBy('tp.date', 'ASC');

        $positions = $qb->getQuery()->getResult();

        return [
            'layout' => $layout,
            'date' => $date,
            'positions' => $positions,
        ];
    }
}
