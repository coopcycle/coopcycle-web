<?php

namespace AppBundle\Command;

use ApiPlatform\Core\Api\IriConverterInterface;
use AppBundle\Entity\ApiApp;
use AppBundle\Entity\Woopit\Delivery;
use AppBundle\Entity\Woopit\QuoteRequest;
use BenjaminFavre\OAuthHttpClient\OAuthHttpClient;
use Doctrine\ORM\EntityManagerInterface;
use League\Bundle\OAuth2ServerBundle\OAuth2Grants;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use Symfony\Contracts\HttpClient\Exception\HttpExceptionInterface;
use Symfony\Contracts\HttpClient\Exception\TransportExceptionInterface;

class WoopitSubscriptionsCommand extends Command
{
    public function __construct(
        OAuthHttpClient $woopitClient,
        IriConverterInterface $iriConverter,
        EntityManagerInterface $entityManager
    )
    {
        $this->woopitClient = $woopitClient;
        $this->iriConverter = $iriConverter;
        $this->entityManager = $entityManager;

        parent::__construct();
    }

    protected function configure()
    {
        $this
            ->setName('woopit:subscriptions:create')
            ->setDescription('Subscribe to Woopit requests and events.')
            ->addArgument(
                'client_id',
                InputArgument::REQUIRED
            );
    }

    protected function initialize(InputInterface $input, OutputInterface $output)
    {
        $this->io = new SymfonyStyle($input, $output);
    }

    /**
     * @return int
     */
    protected function execute(InputInterface $input, OutputInterface $output)
    {
        $clientId = $input->getArgument('client_id');

        $body = [
            'callbacks' => $this->getCallbacks(),
            'auth' => $this->getAuthConfig($clientId),
        ];

        try {

            $response = $this->woopitClient->request('POST', "subscriptions", [
                'json' => $body
            ]);

            $statusCode = $response->getStatusCode();

            switch($statusCode) {
                case 204:
                    $this->io->text('Subscriptions for Woopit processed successfully');
                    break;
                case 400:
                    $responseData = json_decode((string) $response->getContent(false), true);
                    $this->io->caution(
                        sprintf('Missing and/or incorrect items in the body. Reasons: %s', $responseData['message'])
                    );
                    break;
                default:
                    $this->io->caution(
                        sprintf('Status code %d not handled', $statusCode)
                    );
                    break;
            }
        } catch (HttpExceptionInterface | TransportExceptionInterface $e) {
            $this->io->caution($e->getMessage());
        }

        return 0;
    }

    private function getCallbacks()
    {
        $defaultVersion = '1.6.0'; // Current Woopit version available

        $quoteUrl = $this->iriConverter->getIriFromResourceClass(QuoteRequest::class, UrlGeneratorInterface::ABSOLUTE_URL);
        $deliveryUrl = $this->iriConverter->getIriFromResourceClass(Delivery::class, UrlGeneratorInterface::ABSOLUTE_URL);

        return [
            "quote" => [
                'url' => $quoteUrl,
                'version' => $defaultVersion,
            ],
            "cancelQuote" => [
                'url' => "${quoteUrl}/{quoteId}",
                'version' => $defaultVersion,
            ],
            "delivery" => [
                'url' => $deliveryUrl,
                'version' => $defaultVersion,
            ],
            "update" => [
                'url' => "${deliveryUrl}/{deliveryId}",
                'version' => $defaultVersion,
            ],
            "cancelDelivery" => [
                'url' => "${deliveryUrl}/{deliveryId}",
                'version' => $defaultVersion,
            ],
        ];
    }

    private function getAuthConfig($clientId)
    {
        $app = $this->entityManager->getRepository(ApiApp::class)
            ->findOneBy(['oauth2Client' => $clientId]);

        if (!$app) {
            $this->io->caution(sprintf('App with client_id %s does not exist', $clientId));
            return 1;
        }

        return [
            'client_id' => $clientId,
            'client_secret' => $app->getOauth2Client()->getSecret(),
            'grant_type' => OAuth2Grants::CLIENT_CREDENTIALS,
            'tokenEndpointUrl' => '', // TODO: Which URL should we provide?
        ];
    }
}
