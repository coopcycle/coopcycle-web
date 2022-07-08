<?php

namespace AppBundle\Api\DataTransformer;

use ApiPlatform\Core\DataTransformer\DataTransformerInterface;
use AppBundle\Entity\Delivery;
use AppBundle\Entity\DeliveryQuote;
use AppBundle\Entity\Package;
use AppBundle\Entity\Store;
use AppBundle\Entity\Task;
use AppBundle\Api\Resource\RetailPrice;
use AppBundle\Service\RoutingInterface;
use ApiPlatform\Core\Api\IriConverterInterface;
use Doctrine\Persistence\ManagerRegistry;

class DeliveryInputDataTransformer implements DataTransformerInterface
{
    public function __construct(
        RoutingInterface $routing,
        IriConverterInterface $iriConverter,
        ManagerRegistry $doctrine)
    {
        $this->routing = $routing;
        $this->iriConverter = $iriConverter;
        $this->doctrine = $doctrine;
    }

    /**
     * {@inheritdoc}
     */
    public function transform($data, string $to, array $context = [])
    {
        if (is_array($data->tasks) && count($data->tasks) > 0) {
            $delivery = Delivery::createWithTasks(...$data->tasks);
        } else {
            $delivery = Delivery::createWithTasks($data->pickup, $data->dropoff);
        }

        if ($data->store && $data->store instanceof Store) {
            $delivery->setStore($data->store);
        }

        if ($data->packages && is_array($data->packages)) {
            $packageRepository = $this->doctrine->getRepository(Package::class);
            foreach ($data->packages as $p) {
                $package = $packageRepository->findOneByName($p['type']);
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

        return in_array($to, [ RetailPrice::class, DeliveryQuote::class ]) && null !== ($context['input']['class'] ?? null);
    }
}
