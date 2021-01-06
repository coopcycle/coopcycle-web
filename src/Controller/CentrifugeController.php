<?php

namespace AppBundle\Controller;

use phpcent\Client as CentrifugoClient;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;

class CentrifugeController extends AbstractController
{
    /**
     * @see https://centrifugal.github.io/centrifugo/server/connection_expiration/
     *
     * @Route("/centrifuge/refresh", name="centrifuge_refresh", methods={"POST"})
     */
    public function refreshAction(Request $request, CentrifugoClient $centrifugoClient)
    {
        $user = $this->getUser();

        if (!$user) {
            return new Response('', 403);
        }

        return new JsonResponse([
            'token' => $centrifugoClient->generateConnectionToken($user->getUsername(), (time() + 3600)),
        ]);
    }

    /**
     * @see https://centrifugal.github.io/centrifugo/server/private_channels/
     * @see https://github.com/centrifugal/centrifuge-js#private-channels-subscription
     *
     * @Route("/centrifuge/subscribe", name="centrifuge_subscribe", methods={"POST"})
     */
    public function subscribeAction(Request $request, CentrifugoClient $centrifugoClient)
    {
        $data = [];
        $content = $request->getContent();
        if (!empty($content)) {
            $data = json_decode($content, true);
        }

        // {
        //     "client": "<CLIENT ID>",
        //     "channels": ["$chan1", "$chan2"]
        // }

        $response = [
            'channels' => []
        ];

        $trackingChannel = sprintf('$%s_tracking', $this->getParameter('centrifugo_namespace'));

        foreach ($data['channels'] as $channel) {
            if ($channel === $trackingChannel && $this->isGranted('ROLE_ADMIN')) {
                $response['channels'][] = [
                    'channel' => $channel,
                    'token' => $centrifugoClient->generatePrivateChannelToken($data['client'], $channel, (time() + 3600)),
                ];
            }
        }

        // {
        //     "channels": [
        //         {
        //             "channel": "$chan1",
        //             "token": "<SUBSCRIPTION JWT TOKEN>"
        //         },
        //         {
        //             "channel": "$chan2",
        //             "token": <SUBSCRIPTION JWT TOKEN>
        //         }
        //     ]
        // }

        return new JsonResponse($response);
    }
}
