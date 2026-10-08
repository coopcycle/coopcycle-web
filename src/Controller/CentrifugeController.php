<?php

namespace AppBundle\Controller;

use phpcent\Client as CentrifugoClient;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

class CentrifugeController extends AbstractController
{
    /**
     * @see https://centrifugal.github.io/centrifugo/server/connection_expiration/
     */
    #[Route(path: '/centrifuge/refresh', name: 'centrifuge_refresh', methods: ['POST'])]
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
     * Issues a subscription token for one channel, for SDKs from centrifuge-js v3
     * on (`getToken` on a subscription).
     *
     * Our Centrifugo only lets a connection subscribe unaided to its own
     * user-limited channels (`..._events#<username>`). Anything else -- the
     * tracking channel the dispatch board reads courier positions from -- is
     * refused with "permission denied" unless the client presents a token, so
     * this is what stands between a dispatcher and a map that never moves.
     *
     * The older /centrifuge/subscribe below serves the same purpose for clients
     * still on centrifuge-js v2, which the mobile app is. Both are needed until
     * that ships.
     */
    #[Route(path: '/centrifuge/subscription-token', name: 'centrifuge_subscription_token', methods: ['POST'])]
    public function subscriptionTokenAction(Request $request, CentrifugoClient $centrifugoClient)
    {
        // An empty or malformed body throws a JsonException, which Symfony turns
        // into a 400 on its own.
        $data = $request->toArray();

        $channel = $data['channel'] ?? null;
        $client = $data['client'] ?? null;

        if (empty($channel) || empty($client)) {
            return new JsonResponse(['message' => 'Both "channel" and "client" are required'], 400);
        }

        if (!$this->canSubscribeTo($channel)) {
            return new JsonResponse(['message' => 'Not allowed to subscribe to this channel'], 403);
        }

        return new JsonResponse([
            'token' => $centrifugoClient->generateSubscriptionToken($client, $channel, (time() + 3600)),
        ]);
    }

    /**
     * Whether the current user may subscribe to $channel.
     *
     * Deliberately a whitelist. The channels that carry one user's own events do
     * not come through here -- Centrifugo authorises those itself from the
     * connection token -- so anything asking for a token is asking for something
     * shared, and should have to be named.
     */
    private function canSubscribeTo(string $channel): bool
    {
        $trackingChannel = sprintf('$%s_tracking', $this->getParameter('centrifugo_namespace'));

        if ($channel === $trackingChannel) {
            // Courier positions are rendered by the dispatch board, which is also
            // open to dispatchers -- not only admins.
            return $this->isGranted('ROLE_ADMIN') || $this->isGranted('ROLE_DISPATCHER');
        }

        return false;
    }

    /**
     * @see https://centrifugal.github.io/centrifugo/server/private_channels/
     * @see https://github.com/centrifugal/centrifuge-js#private-channels-subscription
     */
    #[Route(path: '/centrifuge/subscribe', name: 'centrifuge_subscribe', methods: ['POST'])]
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
                    'token' => $centrifugoClient->generateSubscriptionToken($data['client'], $channel, (time() + 3600)),
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
