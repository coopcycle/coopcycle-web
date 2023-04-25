<?php

namespace AppBundle\MessageHandler;

use ApiPlatform\Core\Api\IriConverterInterface;
use AppBundle\Entity\Delivery;
use AppBundle\Entity\TaskImage;
use AppBundle\Message\WoopitDocumentWebhook;
use BenjaminFavre\OAuthHttpClient\OAuthHttpClient;
use Doctrine\ORM\EntityManagerInterface;
use Exception;
use Hashids\Hashids;
use Liip\ImagineBundle\Service\FilterService;
use Psr\Log\LoggerInterface;
use Psr\Log\NullLogger;
use Symfony\Component\Messenger\Handler\MessageHandlerInterface;
use Symfony\Component\Mime\Part\DataPart;
use Symfony\Component\Mime\Part\Multipart\FormDataPart;
use Symfony\Contracts\HttpClient\Exception\HttpExceptionInterface;
use Symfony\Contracts\HttpClient\Exception\TransportExceptionInterface;
use Vich\UploaderBundle\Templating\Helper\UploaderHelper;

class WoopitDocumentHandler implements MessageHandlerInterface
{

    public function __construct(
        OAuthHttpClient $woopitClient,
        IriConverterInterface $iriConverter,
        EntityManagerInterface $entityManager,
        Hashids $hashids12,
        UploaderHelper $uploaderHelper,
        FilterService $imagineFilter,
        LoggerInterface $logger = null)
    {
        $this->woopitClient = $woopitClient;
        $this->iriConverter = $iriConverter;
        $this->entityManager = $entityManager;
        $this->hashids12 = $hashids12;
        $this->uploaderHelper = $uploaderHelper;
        $this->imagineFilter = $imagineFilter;
        $this->logger = $logger ?? new NullLogger();
    }

    public function __invoke(WoopitDocumentWebhook $message)
    {
        $taskImage = $this->iriConverter->getItemFromIri($message->getObject());

        if (!$taskImage instanceof TaskImage) {
            return;
        }

        $imagePath = $this->getTaskImagePath($taskImage);

        // See https://symfony.com/doc/current/http_client.html#uploading-data
        // DataPart::fromPath only reads files from local
        // Download file locally
        $tempDir = sys_get_temp_dir();
        $tempnam = tempnam($tempDir, 'woopit_task_image');

        if (false === file_put_contents($tempnam, file_get_contents($imagePath))) {
            $this->logger->error('[WOOPIT] file_put_contents Could not write temp file');
            return 1;
        }

        try {
            $data = [
                'type' => $message->getType(),
                'document' => DataPart::fromPath($tempnam),
                'date' => $taskImage->getCreatedAt()->format(\DateTime::ATOM)
            ];
            $formData = new FormDataPart($data);

            $this->request($taskImage->getTask()->getDelivery(), $formData);
        } catch(Exception $e) {
            $this->logger->error(sprintf('[WOOPIT] __invoke %s', $e->getMessage()));
        }
    }

    private function request(Delivery $delivery, FormDataPart $formData)
    {
        $deliveryId = $this->hashids12->encode($delivery->getId());

        try {
            $this->logger->info(
                sprintf('[WOOPIT] Sending new document to Woopit for delivery with id %s', $deliveryId)
            );

            $response = $this->woopitClient->request('POST', "deliveries/${deliveryId}/documents", [
                'headers' => $formData->getPreparedHeaders()->toArray(),
                'body' => $formData->bodyToIterable(),
            ]);

            $statusCode = $response->getStatusCode();

            switch($statusCode) {
                case 200:
                    $this->logger->info(
                        sprintf('[WOOPIT] Document request processed successfully for delivery with id %s', $deliveryId)
                    );
                    break;
                case 400:
                    $responseData = json_decode((string) $response->getContent(false), true);
                    $this->logger->error(
                        sprintf('[WOOPIT] Missing and/or incorrect items in the body. Reasons: %s', $responseData['message'])
                    );
                    break;
                case 404:
                    $this->logger->error(
                        sprintf('[WOOPIT] Delivery with id %s was not found', $deliveryId)
                    );
                    break;
                default:
                    $this->logger->warning(
                        sprintf('[WOOPIT] Status code %d not handled', $statusCode)
                    );
                    break;
            }
        } catch (HttpExceptionInterface | TransportExceptionInterface $e) {
            $this->logger->error(sprintf('[WOOPIT] request %s', $e->getMessage()));
        }
    }

    private function getTaskImagePath(TaskImage $taskImage) {
        try {
            $imagePath = $this->uploaderHelper->asset($taskImage, 'file');
            return $this->imagineFilter->getUrlOfFilteredImage($imagePath, 'task_image_thumbnail');
        } catch(Exception $e) {
            $this->logger->error(sprintf('[WOOPIT] getTaskImagePath %s', $e->getMessage()));
            return null;
        }
    }

}
