<?php

namespace AppBundle\MessageHandler;

use AppBundle\Entity\Cyke\Delivery as CykeDelivery;
use AppBundle\Entity\Delivery;
use AppBundle\Message\DeliveryCreated;
use Carbon\Carbon;
use Doctrine\ORM\EntityManagerInterface;
use libphonenumber\PhoneNumberFormat;
use libphonenumber\PhoneNumberUtil;
use Psr\Log\LoggerInterface;
use Psr\Log\NullLogger;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;
use Symfony\Contracts\HttpClient\Exception\HttpExceptionInterface;
use Symfony\Contracts\HttpClient\Exception\TransportExceptionInterface;
use Symfony\Contracts\HttpClient\HttpClientInterface;

#[AsMessageHandler]
class CreateCykeDelivery
{
    private $logger;

    public function __construct(
        private HttpClientInterface $cykeClient,
        private EntityManagerInterface $entityManager,
        private PhoneNumberUtil $phoneNumberUtil,
        ?LoggerInterface $logger = null)
    {
        $this->logger = $logger ?? new NullLogger();
    }

    public function __invoke(DeliveryCreated $message)
    {
        $delivery = $this->entityManager->getRepository(Delivery::class)->find($message->getDeliveryId());

        if (!$delivery) {
            return;
        }

        $store = $delivery->getStore();

        if (null === $store || !$store->isCykeEnabled()) {
            return;
        }

        $existing = $this->entityManager
            ->getRepository(CykeDelivery::class)
            ->findOneBy(['delivery' => $delivery]);

        if (null !== $existing) {
            return;
        }

        $this->logger->info(
            sprintf('Notifying Cyke for delivery #%d', $delivery->getId())
        );

        $dropoff = $delivery->getDropoff();
        $address = $dropoff->getAddress();

        $telephone = $address->getTelephone();

        $payload = [
            'dropoff' => [
                'slot_starting_at' => Carbon::instance($dropoff->getAfter())->toIso8601String(),
                'slot_ending_at' => Carbon::instance($dropoff->getBefore())->toIso8601String(),
                'place' => [
                    'recipient_name' => $address->getContactName() ?: $address->getName(),
                    'recipient_phone' => $telephone ? $this->phoneNumberUtil->format($telephone, PhoneNumberFormat::E164) : null,
                    'company_name' => $address->getCompany(),
                    'address' => $address->getStreetAddress(),
                    'postal_code' => $address->getPostalCode(),
                    'city' => $address->getAddressLocality(),
                    'address_instructions' => $address->getDescription(),
                ],
            ],
            'packages' => array_values(array_map(fn($packageWithQuantity) => [
                'name' => $packageWithQuantity->getPackage()->getName(),
                'amount' => $packageWithQuantity->getQuantity(),
            ], $delivery->getPackages()->toArray())),
            'comments' => $dropoff->getComments(),
            'client_order_reference' => (string) $delivery->getId(),
        ];

        try {
            $response = $this->cykeClient->request('POST', 'deliveries', [
                'headers' => [
                    'X-User-Email' => $store->getCykeUserEmail(),
                    'X-User-Token' => $store->getCykeUserToken(),
                ],
                'json' => $payload,
            ]);

            $data = $response->toArray();

            $cykeDelivery = new CykeDelivery();
            $cykeDelivery->setDelivery($delivery);
            $cykeDelivery->setCykeId((string) $data['id']);

            $this->entityManager->persist($cykeDelivery);
            $this->entityManager->flush();

        } catch (HttpExceptionInterface | TransportExceptionInterface $e) {
            $this->logger->error($e->getMessage());
        }
    }
}
