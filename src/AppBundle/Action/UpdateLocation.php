<?php

namespace AppBundle\Action;

use AppBundle\Action\Utils\TokenStorageTrait;
use AppBundle\Service\SocketIoManager;
use Doctrine\Common\Persistence\ManagerRegistry as DoctrineRegistry;
use Predis\Client as Redis;
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
    protected $socketIoManager;
    protected $logger;

    public function __construct(
        TokenStorageInterface $tokenStorage,
        DoctrineRegistry $doctrine,
        Redis $redis,
        SocketIoManager $socketIoManager,
        LoggerInterface $logger)
    {
        $this->tokenStorage = $tokenStorage;
        $this->doctrine = $doctrine;
        $this->redis = $redis;
        $this->socketIoManager = $socketIoManager;
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

        $this->socketIoManager->toAdmins('tracking', [
            'user' => $username,
            'coords' => [
                'lat' => (float) $lastLocation['latitude'],
                'lng' => (float) $lastLocation['longitude'],
            ]
        ]);

         $this->socketIoManager->toCourier($this->getUser(), 'tracking', [
            'user' => $username,
            'coords' => [
                'lat' => (float) $lastLocation['latitude'],
                'lng' => (float) $lastLocation['longitude'],
            ]
        ]);

        return new JsonResponse([]);
    }
}
