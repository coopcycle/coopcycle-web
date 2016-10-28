<?php

namespace AppBundle\Controller;

use Dunglas\ApiBundle\Controller\ResourceController;
use Symfony\Component\HttpFoundation\Request;
use Nelmio\ApiDocBundle\Annotation\ApiDoc;
use Dunglas\ApiBundle\JsonLd\Response;

class OrderController /* extends ResourceController */
{
    /**
     * Customize the AppBundle:Custom:custom action
     */
    public function updateStatusAction($id, Request $request)
    {
        // $redis = new \Redis();
        // if (!$redis->connect('127.0.0.1', 6379)) {
        //     return new Response('Could not connect to Redis');
        // }

        // $data = json_decode($request->getContent(), true);
        // $coordinates = $data['coordinates'];

        $resource = $this->getResource($request);
        $object = $this->findOrThrowNotFound($resource, $id);

        // $redis->geoadd('Couriers', $coordinates['latitude'], $coordinates['longitude'], "courier_{$id}");

        return new Response();
    }

    // /**
    //  * @ApiDoc(section = "Courier")
    //  */
    // public function getCoordinatesAction(Request $request)
    // {
    //     $redis = new \Redis();
    //     if (!$redis->connect('127.0.0.1', 6379)) {
    //         return new Response('Could not connect to Redis');
    //     }

    //     $distance = $request->query->get('distance');
    //     $latitude = $request->query->get('latitude');
    //     $longitude = $request->query->get('longitude');

    //     $data = array();

    //     // $couriers = $redis->georadius('Couriers', 0, -1);
    //     // foreach ($couriers as $courierKey) {
    //     //     $geopos = $redis->geopos('Couriers', $courierKey);
    //     //     $geopos = current($geopos);
    //     //     $geopos = array_map('floatval', $geopos);
    //     //     $data[] = array(
    //     //         'name' => $courierKey,
    //     //         'coordinate' => $geopos,
    //     //     );
    //     // }

    //     $couriers = $redis->georadius('Couriers', $latitude, $longitude, $distance, 'm', array('WITHCOORD'));

    //     $data = array_map(function($courier) {
    //         return array(
    //             'name' => $courier[0],
    //             'coordinate' => array_map('floatval', $courier[1]),
    //         );
    //     }, $couriers);

    //     return new Response(
    //         $this->get('serializer')->normalize($data, 'json')
    //     );

    //     // return new Response(print_r($response, 1));
    // }
}