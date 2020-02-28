<?php

namespace AppBundle\MessageHandler;

use AppBundle\Entity\Base\GeoCoordinates;
use AppBundle\Entity\TaskList;
use AppBundle\Message\Location;
use AppBundle\Service\RoutingInterface;
use Carbon\Carbon;
use Doctrine\ORM\EntityManagerInterface;
use FOS\UserBundle\Model\UserManagerInterface;
use Predis\Client as Redis;
use Symfony\Component\Messenger\Handler\MessageHandlerInterface;

class CalculateEtaHandler implements MessageHandlerInterface
{
    public function __construct(
        EntityManagerInterface $doctrine,
        UserManagerInterface $userManager,
        RoutingInterface $routing,
        Redis $redis)
    {
        $this->doctrine = $doctrine;
        $this->userManager = $userManager;
        $this->routing = $routing;
        $this->redis = $redis;
    }

    public function __invoke(Location $message)
    {
        if (!$user = $this->userManager->findUserByUsername($message->getUsername())) {
            return;
        }

        $now = Carbon::now();

        if ($taskList = $this->doctrine->getRepository(TaskList::class)->findOneByUserAndDate($user, $now)) {

            if (count($taskList->getTasks()) === 0) {
                return;
            }

            $admins = $this->userManager->findUsersByRole('ROLE_ADMIN');

            $coordinates = [
                new GeoCoordinates(...$message->getCoordinates())
            ];
            foreach ($taskList->getTasks() as $task) {
                $coordinates[] = $task->getAddress()->getGeo();
            }

            $durations = $this->routing->getEtas(...$coordinates);

            $etas = [];
            foreach ($taskList->getTasks() as $i => $task) {
                $duration = $durations[$i];
                $eta = $now->addSeconds($duration);

                $this->redis->set(
                    sprintf('task:%d:eta', $task->getId()),
                    $eta->format(\DateTime::ATOM)
                );

                $etas['/api/tasks/'.$task->getId()] = $eta->format(\DateTime::ATOM);
            }

            foreach ($admins as $admin) {
                $payload = json_encode([
                    'name' => 'etas',
                    'data' => $etas
                ]);
                $this->redis->publish(
                    sprintf('users:%s', $admin->getUsername()),
                    $payload
                );
            }
        }
    }
}
