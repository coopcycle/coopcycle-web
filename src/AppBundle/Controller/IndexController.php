<?php

namespace AppBundle\Controller;

use AppBundle\Entity\Delivery;
use Symfony\Bundle\FrameworkBundle\Controller\Controller;
use Sensio\Bundle\FrameworkExtraBundle\Configuration\Route;
use Sensio\Bundle\FrameworkExtraBundle\Configuration\Template;
use Symfony\Component\HttpFoundation\Request;

/**
 * @Route("/{_locale}", requirements={ "_locale": "%locale_regex%" })
 */
class IndexController extends Controller
{
    /**
     * @Route("/", name="homepage")
     * @Template
     */
    public function indexAction()
    {
        $user = $this->getUser();

        if ($user) {
            $addresses = $user->getAddresses();
        }
        else {
            $addresses = [];
        }

        return array(
            'addresses' => $addresses
        );
    }

    /**
     * @Route("/tracking/viz", name="tracking_viz")
     * @Template
     */
    public function trackingAction()
    {
        $qb = $this->getDoctrine()
            ->getRepository(Delivery::class)
            ->createQueryBuilder('d');

        $qb->andWhere('d.status IN (:statusList)')
            ->setParameter('statusList', [
                Delivery::STATUS_WAITING,
                Delivery::STATUS_DISPATCHED,
                Delivery::STATUS_PICKED
            ]);

        $deliveries = $qb->getQuery()->getResult();
        $deliveries = array_map(function ($delivery) {
            return $this->get('api_platform.serializer')->normalize($delivery, 'jsonld', [
                'resource_class' => Delivery::class,
                'operation_type' => 'item',
                'item_operation_name' => 'get',
                'groups' => ['delivery', 'place']
            ]);
        }, $deliveries);

        return [
            'deliveries' => $deliveries
        ];
    }
}
