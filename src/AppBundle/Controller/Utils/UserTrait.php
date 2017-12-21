<?php

namespace AppBundle\Controller\Utils;

use AppBundle\Entity\TrackingPosition;
use FOS\UserBundle\Model\UserInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;

trait UserTrait
{
    protected function userTracking(UserInterface $user, $layout = 'profile')
    {
        $positions = $this->getDoctrine()->getRepository(TrackingPosition::class)->findBy([
            'courier' => $user,
        ], ['date' => 'ASC']);

        return [
            'layout' => $layout,
            'positions' => $positions,
        ];
    }
}
