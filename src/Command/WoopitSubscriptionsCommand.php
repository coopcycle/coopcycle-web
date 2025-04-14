<?php

namespace AppBundle\Command;

use AppBundle\Entity\ApiApp;
use BenjaminFavre\OAuthHttpClient\OAuthHttpClient;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use Symfony\Contracts\HttpClient\Exception\HttpExceptionInterface;
use Symfony\Contracts\HttpClient\Exception\TransportExceptionInterface;

class WoopitSubscriptionsCommand extends Command
{
    private $io;

    public function __construct(
        private OAuthHttpClient $woopitClient,
        private EntityManagerInterface $entityManager,
        private UrlGeneratorInterface $urlGenerator,
        private string $apiVersion)
    {
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
            )
            ->addOption(
                'update',
                'u',
                InputOption::VALUE_NONE
            );;
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
        $update = $input->getOption('update');

        $app = $this->entityManager->getRepository(ApiApp::class)
            ->findOneBy(['oauth2Client' => $clientId]);

        if (!$app) {
            $this->io->caution(sprintf('App with client_id %s does not exist', $clientId));
            return 1;
        }

        if ($app->getType() !== 'api_key') {
            $this->io->caution(sprintf('App with client_id %s is not using API Key authentication', $clientId));
            return 1;
        }

        $body = [
            'callbacks' => $this->getCallbacks(),
            'headers' => [
                [
                    'key' => 'Authorization',
                    'value' => sprintf('Bearer %s', $app->getApiKey()),
                ],
            ]
        ];

        try {

            $method = $update ? 'PATCH' : 'POST';
            $response = $this->woopitClient->request($method, 'subscriptions', [
                'json' => $body
            ]);

            $statusCode = $response->getStatusCode();

            switch($statusCode) {
                case 204:
                    $this->io->text('Subscriptions for Woopit processed successfully');
                    break;
                case 400:
                    $responseData = json_decode($response->getContent(false), true);
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
        $quoteUrl = $this->urlGenerator->generate('api_quote_requests_get_collection', [], UrlGeneratorInterface::ABSOLUTE_URL);
        $deliveryUrl = $this->urlGenerator->generate('api_quote_requests_post_deliveries_collection', [], UrlGeneratorInterface::ABSOLUTE_URL);

        return [
            "quote" => [
                'url' => $quoteUrl,
                'version' => $this->apiVersion,
            ],
            "cancelQuote" => [
                'url' => "{$quoteUrl}/{quoteId}",
                'version' => $this->apiVersion,
            ],
            "delivery" => [
                'url' => $deliveryUrl,
                'version' => $this->apiVersion,
            ],
            "update" => [
                'url' => "{$deliveryUrl}/{deliveryId}",
                'version' => $this->apiVersion,
            ],
            "cancelDelivery" => [
                'url' => "{$deliveryUrl}/{deliveryId}",
                'version' => $this->apiVersion,
            ],
        ];
    }
}
