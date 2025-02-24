<?php

namespace AppBundle\Api\DataTransformer;

use ApiPlatform\Core\DataTransformer\DataTransformerInterface;
use AppBundle\Entity\Delivery;
use AppBundle\Entity\DeliveryQuote;
use AppBundle\Entity\Package;
use AppBundle\Entity\Store;
use AppBundle\Entity\Task;
use AppBundle\Api\Resource\RetailPrice;
use AppBundle\Service\DeliveryManager;
use AppBundle\Service\RoutingInterface;
use Doctrine\Persistence\ManagerRegistry;
use Symfony\Component\Security\Core\Authorization\AuthorizationCheckerInterface;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;

class DeliveryInputDataTransformer implements DataTransformerInterface
{
    public function __construct(
        private readonly RoutingInterface $routing,
        private readonly ManagerRegistry $doctrine,
        private readonly DeliveryManager $deliveryManager,
        private readonly AuthorizationCheckerInterface $authorizationChecker)
    {
    }

    /**
     * {@inheritdoc}
     */
    public function transform($data, string $to, array $context = [])
    {
        if (is_array($data->tasks) && count($data->tasks) > 0) {
            $delivery = Delivery::createWithTasks(...$data->tasks);
        } else {
            if ($data->pickup && $data->dropoff) {
                $delivery = Delivery::createWithTasks($data->pickup, $data->dropoff);
            } else {
                $delivery = Delivery::create();
                $delivery->removeTask($delivery->getDropoff());

                $pickup = $delivery->getPickup();
                $dropoff = $data->dropoff;

                $pickup->setNext($dropoff);
                $dropoff->setPrevious($pickup);

                $delivery->addTask($dropoff);
            }
        }

        if ($data->store && $data->store instanceof Store) {

            //FIXME: move access controls to the operations in the Entities
            if (!$this->authorizationChecker->isGranted('ROLE_DISPATCHER') && !$this->authorizationChecker->isGranted('edit', $data->store)) {
                throw new AccessDeniedHttpException('');
            }

            $delivery->setStore($data->store);
        }

        $this->deliveryManager->setDefaults($delivery);

        if ($data->packages && is_array($data->packages)) {
            $packageRepository = $this->doctrine->getRepository(Package::class);

            foreach ($data->packages as $p) {
                $package = $packageRepository->findOneByNameAndStore($p['type'], $delivery->getStore());
                if ($package) {
                    $delivery->addPackageWithQuantity($package, $p['quantity']);
                }
            }
        }

        $coords = array_map(fn ($task) => $task->getAddress()->getGeo(), $delivery->getTasks());
        $distance = $this->routing->getDistance(...$coords);

        $delivery->setDistance(ceil($distance));
        $delivery->setWeight($data->weight ?? null);

        return $delivery;
    }

    /**
     * {@inheritdoc}
     */
    public function supportsTransformation($data, string $to, array $context = []): bool
    {
        if ($data instanceof RetailPrice) {
          return false;
        }

        if ($data instanceof DeliveryQuote) {
          return false;
        }

        if ($data instanceof Delivery) {
          return false;
        }

        return in_array($to, [ RetailPrice::class, DeliveryQuote::class, Delivery::class ]) && null !== ($context['input']['class'] ?? null);
    }
}
