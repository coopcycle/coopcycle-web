<?php

namespace AppBundle\Action;

use AppBundle\Action\Utils\TokenStorageTrait;
use Doctrine\Persistence\ManagerRegistry;
use Redis;
use Predis\Client as Tile38Client;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\Security\Core\Authentication\Token\Storage\TokenStorageInterface;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Psr\Log\LoggerInterface;

class UpdateLocation
{
    use TokenStorageTrait;

    protected $doctrine;
    protected $redis;
    protected $tile38;
    protected $fleetKey;
    protected $logger;

    public function __construct(
        TokenStorageInterface $tokenStorage,
        ManagerRegistry $doctrine,
        Redis $redis,
        Tile38Client $tile38,
        string $fleetKey,
        LoggerInterface $logger)
    {
        $this->tokenStorage = $tokenStorage;
        $this->doctrine = $doctrine;
        $this->redis = $redis;
        $this->tile38 = $tile38;
        $this->fleetKey = $fleetKey;
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
            $location['time'] = ((int) $location['time']) / 1000;
            return $location;
        }, $data);

        usort($data, function ($a, $b) {
            return $a['time'] < $b['time'] ? -1 : 1;
        });

        foreach ($data as $location) {
            $key = sprintf('tracking:%s', $username);
            $this->redis->rpush($key, json_encode([
                'latitude' => (float) $location['latitude'],
                'longitude' => (float) $location['longitude'],
                'timestamp' => (int) $location['time'],
            ]));
        }

        $lastLocation = array_pop($data);

        $datetime = new \DateTime();
        $datetime->setTimestamp($lastLocation['time']);

        $this->logger->info(sprintf('Last position recorded at %s', $datetime->format('Y-m-d H:i:s')));

        // SET fleet truck1 POINT 3.5123 -12.2693

        $response =
            $this->tile38->executeRaw(['SET',
                $this->fleetKey,
                $username,
                'POINT',
                $lastLocation['latitude'],
                $lastLocation['longitude'],
                $lastLocation['time'],
            ]);

        // EXPIRE fleet truck 10

        $response =
            $this->tile38->executeRaw(['EXPIRE', $this->fleetKey, $username, 60 * 30]);

        return new JsonResponse([]);
    }
}
