<?php

namespace AppBundle\Action;

use AppBundle\Action\Utils\TokenStorageTrait;
use AppBundle\Message\UpdateLocation as UpdateLocationMessage;
use phpcent\Client as CentrifugoClient;
use Redis;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\Security\Core\Authentication\Token\Storage\TokenStorageInterface;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Messenger\MessageBusInterface;
use Psr\Log\LoggerInterface;

class UpdateLocation
{
    use TokenStorageTrait;

    protected $messageBus;
    protected $tile38;
    protected $fleetKey;
    protected $logger;

    public function __construct(
        TokenStorageInterface $tokenStorage,
        MessageBusInterface $messageBus,
        Redis $tile38,
        string $fleetKey,
        CentrifugoClient $centrifugo,
        string $trackingChannel,
        string $centrifugoNamespace,
        LoggerInterface $logger)
    {
        $this->tokenStorage = $tokenStorage;
        $this->messageBus = $messageBus;
        $this->tile38 = $tile38;
        $this->fleetKey = $fleetKey;
        $this->centrifugo = $centrifugo;
        $this->trackingChannel = $trackingChannel;
        $this->centrifugoNamespace = $centrifugoNamespace;
        $this->logger = $logger;
    }

    /**
     * @Route(path="/me/location", name="me_location", methods={"POST"})
     */
    public function locationAction(Request $request)
    {
        $data = [];
        $content = $request->getContent();
        if (!empty($content)) {
            $data = json_decode($content, true);
        }

        $username = $this->getUser()->getUsername();

        if (count($data) === 0) {
            return new JsonResponse([]);
        }

        $data = array_map(function ($location) {

            // Using transistorsoft/react-native-background-geolocation
            if (is_string($location['time'])) {
                $location['time'] = strtotime($location['time']);
            // Using mauron85/react-native-background-geolocation
            } else {
                $location['time'] = ((int) $location['time']) / 1000;
            }

            return $location;
        }, $data);

        usort($data, function ($a, $b) {
            return $a['time'] < $b['time'] ? -1 : 1;
        });

        $locations = [];
        foreach ($data as $location) {
            $locations[] = [
                'latitude'  => (float) $location['latitude'],
                'longitude' => (float) $location['longitude'],
                'timestamp' => (int) $location['time'],
            ];
        }

        $this->messageBus->dispatch(
            new UpdateLocationMessage($username, $locations)
        );

        $lastLocation = array_pop($data);

        $datetime = new \DateTime();
        $datetime->setTimestamp($lastLocation['time']);

        $this->logger->info(sprintf('Last position recorded at %s', $datetime->format('Y-m-d H:i:s')));

        // SET fleet truck1 POINT 3.5123 -12.2693

        $response =
            $this->tile38->rawCommand(
                'SET',
                $this->fleetKey,
                $username,
                'POINT',
                $lastLocation['latitude'],
                $lastLocation['longitude'],
                $lastLocation['time']
            );

        // EXPIRE fleet truck 10

        $response =
            $this->tile38->rawCommand('EXPIRE', $this->fleetKey, $username, (60 * 30));

        // Even if the current position stored in Tile38 should be the source of thruth,
        // we send an update immediately using Centrifugo
        // Utimately, this should be implemented using a worker that listens to a Tile38 CHAN

        $this->logger->info(sprintf('Publishing message to Centrifugo channel "%s"', $this->trackingChannel));

        $locationMsg = [
            'user' => $username,
            'coords' => [
                'lat' => $lastLocation['latitude'],
                'lng' => $lastLocation['longitude'],
            ],
            'ts' => $lastLocation['time'],
        ];
        $courierChannel = sprintf('%s_tracking#%s', $this->centrifugoNamespace, $username);

        $this->centrifugo->publish($this->trackingChannel, $locationMsg);
        $this->centrifugo->publish($courierChannel, $locationMsg);

        return new JsonResponse([]);
    }
}
