<?php

namespace AppBundle\Command;

use AppBundle\CubeJs\TokenFactory as CubeJsTokenFactory;
use AppBundle\Entity\CubeJsonQuery;
use AppBundle\Service\EmailManager;
use Doctrine\ORM\EntityManagerInterface;
use League\Csv\Writer as CsvWriter;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;
use Symfony\Component\Mime\Part\DataPart;
use Symfony\Contracts\HttpClient\HttpClientInterface;
use Twig\Environment as TwigEnvironment;

Class EmailCubeQueryResultCommand extends Command
{
    public function __construct(
        private CubeJsTokenFactory $tokenFactory,
        private HttpClientInterface $cubejsClient,
        private TwigEnvironment $twig,
        private EmailManager $emailManager,
        private EntityManagerInterface $entityManager)
    {
        parent::__construct();
    }

    protected function configure()
    {
        $this
            ->setName('coopcycle:cube:email-results')
            ->setDescription('Emails results of a Cube query.')
            ->addArgument(
                'query',
                InputArgument::REQUIRED,
                'Name of the JSON query stored in database'
            )
            ->addOption(
                'email',
                null,
                InputOption::VALUE_REQUIRED | InputOption::VALUE_IS_ARRAY,
                'User(s) to send the export to'
            )
            ;
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
        $queryName = $input->getArgument('query');

        $cubeQuery = $this->entityManager->getRepository(CubeJsonQuery::class)->findOneByName($queryName);

        $template = $this->twig->createTemplate(json_encode($cubeQuery->getQuery()));
        $parsedQuery = $template->render([]);

        $jsonQuery = json_decode($parsedQuery, true);

        $cubeJsToken = $this->tokenFactory->createToken();

        $response = $this->cubejsClient->request('POST', 'load', [
            'headers' => [
                'Authorization' => $cubeJsToken,
                'Content-Type' => 'application/json',
            ],
            'body' => json_encode([
              'query' => $jsonQuery
            ])
        ]);

        $content = $response->getContent();

        $result = json_decode($content, true);

        $annotation = $result['annotation'];

        $columns = $annotation['dimensions'] + $annotation['measures'];
        $header = [];
        foreach ($columns as $key => $props) {
            $header[$key] = $props['shortTitle'];
        }

        $csv = CsvWriter::createFromString('');
        $csv->insertOne(array_values($header));
        foreach ($result['data'] as $data) {

            $row = [];
            foreach (array_keys($header) as $key) {
                $row[$key] = $data[$key];
            }

            $csv->insertOne($row);
        }

        $emails = $input->getOption('email');

        if (count($emails) === 0) {
            $output->writeln($csv->getContent());

            return 0;
        }

        $message = $this->emailManager->createHtmlMessage('Your data export');
        $message->html('Please find attached your data export');
        $message->attach(fopen('data://text/plain,' . $csv->getContent(), 'r'), 'Export.csv', 'text/csv');

        $this->emailManager->sendTo($message, ...$emails);

        return 0;
    }
}

