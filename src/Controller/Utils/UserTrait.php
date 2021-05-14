<?php

namespace AppBundle\Controller\Utils;

use AppBundle\Entity\Address;
use AppBundle\Entity\TrackingPosition;
use Nucleos\UserBundle\Model\UserInterface;
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

        return $this->render('user/tracking.html.twig', [
            'layout' => $layout,
            'date' => $date,
            'positions' => $positions,
        ]);
    }

    protected function getUserAddresses()
    {
        $addresses = [];

        $user = $this->getUser();
        if ($user) {
            $addresses = $user->getAddresses()->toArray();
        }

        return array_map(function ($address) {

            return $this->get('serializer')->normalize($address, 'jsonld', [
                'resource_class' => Address::class,
                'operation_type' => 'item',
                'item_operation_name' => 'get',
                'groups' => ['address']
            ]);
        }, $addresses);
    }
}
