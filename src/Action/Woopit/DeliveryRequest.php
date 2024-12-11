<?php

namespace AppBundle\Action\Woopit;

use AppBundle\Entity\Store;
use AppBundle\Entity\Woopit\QuoteRequest as WoopitQuoteRequest;
use AppBundle\Entity\Woopit\Delivery as WoopitDelivery;
use AppBundle\Entity\Woopit\WoopitIntegration;
use AppBundle\Service\DeliveryManager;
use AppBundle\Service\Geocoder;
use AppBundle\Utils\Barcode\BarcodeUtils;
use Doctrine\ORM\EntityManagerInterface;
use Hashids\Hashids;
use libphonenumber\PhoneNumberUtil;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use Symfony\Component\Validator\Validator\ValidatorInterface;

class DeliveryRequest
{
    use CreateDeliveryTrait;

    private $deliveryManager;
    private $geocoder;

    public function __construct(
        DeliveryManager $deliveryManager,
        Geocoder $geocoder,
        Hashids $hashids12,
        EntityManagerInterface $entityManager,
        PhoneNumberUtil $phoneNumberUtil,
        ValidatorInterface $checkDeliveryValidator,
        private UrlGeneratorInterface $urlGenerator)
    {
        $this->deliveryManager = $deliveryManager;
        $this->geocoder = $geocoder;
        $this->hashids12 = $hashids12;
        $this->entityManager = $entityManager;
        $this->phoneNumberUtil = $phoneNumberUtil;
        $this->checkDeliveryValidator = $checkDeliveryValidator;
    }

    public function __invoke(WoopitQuoteRequest $data)
    {
        $integration = $this->entityManager->getRepository(WoopitIntegration::class)
            ->findOneBy([
                'woopitStoreId' => $data->retailer['store']['id']
            ]);

        if (!$integration) {
            return new JsonResponse([
                "reasons" => [
                    "REFUSED_EXCEPTION"
                ],
                "comments" => sprintf('The store with ID %s does not exist', $data->retailer['store']['id'])
            ], 202);
        }

        $store = $this->entityManager->getRepository(Store::class)
            ->find($integration->getStore()->getId());

        if (!$store) {
            // TODO Throw Exception ??
        }

        $decoded = $this->hashids12->decode($data->quoteId);

        if (count($decoded) !== 1) {
            // TODO Throw Exception (Not Found)
        }

        $id = current($decoded);

        $quoteRequest = $this->entityManager->getRepository(WoopitQuoteRequest::class)->find($id);

        if (!$quoteRequest) {
             // TODO Throw Exception (Not Found)
        }

        $delivery = $this->createDelivery($data, $integration);

        $violations = $this->validateDeliveryWithIntegrationConstraints($data, $delivery, $integration);

        if (null !== $violations) {
            return $violations;
        }

        $woopitDelivery = new WoopitDelivery();
        $woopitDelivery->setDelivery($delivery);

        $store->addDelivery($delivery);

        $this->entityManager->persist($delivery);
        $this->entityManager->persist($woopitDelivery);
        $this->entityManager->flush();

        $data->deliveryObject = $delivery;
        $data->state = WoopitQuoteRequest::STATE_CONFIRMED;

        foreach ($delivery->getTasks() as $task) {

            $barcode = BarcodeUtils::getRawBarcodeFromTask($task);
            $barcodeToken = BarcodeUtils::getToken($barcode);

            $data->labels[] = [
                'id' => sprintf('lbl_%s', $this->hashids12->encode($task->getId())),
                'type' => 'url',
                'mode' => 'pdf',
                'value' => $this->urlGenerator->generate('task_label_pdf', ['code' => $barcode, 'token' => $barcodeToken], UrlGeneratorInterface::ABSOLUTE_URL)
            ];
        }

        return $data;
    }
}
