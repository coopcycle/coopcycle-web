<?php

namespace AppBundle\Controller\Utils;

use AppBundle\Entity\ApiUser;
use AppBundle\Entity\TrackingPosition;
use FOS\UserBundle\Model\UserInterface;
use Symfony\Component\HttpFoundation\JsonResponse;
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

    public function searchUsersAction(Request $request)
    {
        $userManager = $this->get('fos_user.user_manager');

        $users = $userManager->searchUsers($request->query->get('q'));

        if ($request->query->has('format') && 'json' === $request->query->get('format')) {

            $data = array_map(function (ApiUser $user) {

                return [
                    'id' => $user->getId(),
                    'name' => (string) $user,
                    'user' => $this->get('api_platform.serializer')->normalize($user, 'jsonld', [
                        'resource_class' => ApiUser::class,
                        'operation_type' => 'item',
                        'item_operation_name' => 'get',
                        'groups' => ['user']
                    ])
                ];

            }, $users);

            return new JsonResponse($data);
        }
    }
}
