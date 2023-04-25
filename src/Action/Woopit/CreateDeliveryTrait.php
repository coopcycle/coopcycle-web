<?php

namespace AppBundle\Action\Woopit;

use AppBundle\Entity\Woopit\QuoteRequest as WoopitQuoteRequest;
use AppBundle\Entity\Delivery;
use AppBundle\Entity\Task;
use AppBundle\Entity\Woopit\WoopitIntegration;
use AppBundle\Validator\Constraints\CheckDelivery as AssertCheckDelivery;
use Carbon\Carbon;
use Symfony\Component\HttpFoundation\JsonResponse;

trait CreateDeliveryTrait
{
    use PackagesTrait;
    use AddressTrait;

    protected function createDelivery(WoopitQuoteRequest $data, WoopitIntegration $integration): Delivery
    {
        $pickup = $this->createTask($data->picking, Task::TYPE_PICKUP);
        $dropoff = $this->createTask($data->delivery, Task::TYPE_DROPOFF);

        $this->parseAndApplyPackages($data, $pickup);

        $delivery = Delivery::createWithTasks($pickup, $dropoff);

        $this->deliveryManager->setDefaults($delivery);

        $delivery->setStore($integration->getStore());

        return $delivery;
    }

    protected function createTask(array $data, string $type): Task
    {
        $location = $data['location'];

        $streetAddress = sprintf('%s %s %s', $location['addressLine1'], $location['postalCode'], $location['city']);

        $address = $this->geocoder->geocode($streetAddress);

        $address->setDescription(
            $this->getAddressDescription($location)
        );

        if (isset($data['contact'])) {
            $contact = $data['contact'];
            $address->setContactName($contact['firstName'] . ' ' . $contact['lastName']);
            $address->setTelephone($this->phoneNumberUtil->parse($contact['phone']));

            // Should be save $contact['email']? User or guest customer?
        }

        $task = new Task();
        $task->setType($type);
        $task->setAddress($address);

        $task->setAfter(
            Carbon::parse($data['interval'][0]['start'])->toDateTime()
        );
        $task->setBefore(
            Carbon::parse($data['interval'][0]['end'])->toDateTime()
        );

        return $task;
    }

    protected function validateDeliveryWithIntegrationConstraints(WoopitQuoteRequest $data, Delivery $delivery, WoopitIntegration $integration)
    {
        // zone constraints
        $violations = $this->checkDeliveryValidator->validate($delivery, new AssertCheckDelivery());

        if (count($violations) > 0) {
            return new JsonResponse([
                "reasons" => [
                    "REFUSED_AREA"
                ],
                "comments" => "The collection address is in an area that is not covered by our teams"
            ], 202);
        }

        // packages constraints
        if ($data->packages) {

            foreach($data->packages as $package) {

                if (isset($package['weight']) && null !== $integration->getMaxWeight()) {
                    if ($package['weight']['value'] > $integration->getMaxWeight()) {
                        return new JsonResponse([
                            "reasons" => [
                                "REFUSED_TOO_HEAVY"
                            ]
                        ], 202);
                    }
                }

                if (isset($package['width']) && null !== $integration->getMaxWidth()) {
                    if ($package['width']['value'] > $integration->getMaxWidth()) {
                        return new JsonResponse([
                            "reasons" => [
                                "REFUSED_TOO_LARGE"
                            ],
                            "comments" => sprintf('The size of one or more packages exceeds our acceptance limit of %.2f cm', $integration->getMaxWidth())
                        ], 202);
                    }
                }

                if (isset($package['height']) && null !== $integration->getMaxHeight()) {
                    if ($package['height']['value'] > $integration->getMaxHeight()) {
                        return new JsonResponse([
                            "reasons" => [
                                "REFUSED_TOO_LARGE"
                            ],
                            "comments" => sprintf('The size of one or more packages exceeds our acceptance limit of %.2f cm', $integration->getMaxHeight())
                        ], 202);
                    }
                }

                if (isset($package['length']) && null !== $integration->getMaxLength()) {
                    if ($package['length']['value'] > $integration->getMaxLength()) {
                        return new JsonResponse([
                            "reasons" => [
                                "REFUSED_TOO_LARGE"
                            ],
                            "comments" => sprintf('The size of one or more packages exceeds our acceptance limit of %.2f cm', $integration->getMaxLength())
                        ], 202);
                    }
                }

                if (isset($package['products']) && null !== $integration->getProductTypes() && !empty($integration->getProductTypes())) {
                    foreach($package['products'] as $product) {
                        if (!in_array($product['type'], $integration->getProductTypes())) {
                            return new JsonResponse([
                                "reasons" => [
                                    "REFUSED_EXCEPTION"
                                ],
                                "comments" => sprintf('No availability of product type %s', $product['type'])
                            ], 202);
                        }
                    }
                }

            }

        }

        return null;
    }
}
