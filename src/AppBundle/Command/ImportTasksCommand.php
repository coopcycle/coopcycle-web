<?php

namespace AppBundle\Command;

use AppBundle\Entity\Task;
use AppBundle\Entity\Task\Group as TaskGroup;
use AppBundle\Message\ImportTasks;
use AppBundle\Service\RemotePushNotificationManager;
use AppBundle\Service\SocketIoManager;
use AppBundle\Spreadsheet\TaskSpreadsheetParser;
use Doctrine\ORM\EntityManagerInterface;
use Hashids\Hashids;
use League\Flysystem\Filesystem;
use Psr\Log\LoggerInterface;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;
use Symfony\Component\Messenger\Handler\MessageHandlerInterface;
use Symfony\Component\Validator\Validator\ValidatorInterface;

class ImportTasksCommand extends Command
{
    private $objectManager;
    private $taskImportsFilesystem;
    private $spreadsheetParser;
    private $validator;
    private $socketIoManager;
    private $hashids;
    private $logger;

    public function __construct(
        EntityManagerInterface $objectManager,
        Filesystem $taskImportsFilesystem,
        TaskSpreadsheetParser $spreadsheetParser,
        ValidatorInterface $validator,
        SocketIoManager $socketIoManager,
        string $secret,
        LoggerInterface $logger)
    {
        $this->objectManager = $objectManager;
        $this->taskImportsFilesystem = $taskImportsFilesystem;
        $this->spreadsheetParser = $spreadsheetParser;
        $this->validator = $validator;
        $this->socketIoManager = $socketIoManager;
        $this->logger = $logger;
        $this->hashids = new Hashids($secret, 8);

        parent::__construct();
    }

    protected function configure()
    {
        $this
            ->setName('coopcycle:tasks:import')
            ->setDescription('Import tasks')
            ->addArgument(
                'filename',
                InputArgument::REQUIRED
            )
            ->addArgument(
                'token',
                InputArgument::REQUIRED
            )
            ->addOption(
                'date',
                'd',
                InputOption::VALUE_REQUIRED,
                'The default date',
                'now'
            )
            ;
    }

    protected function initialize(InputInterface $input, OutputInterface $output)
    {
        $this->io = new SymfonyStyle($input, $output);
    }

    protected function execute(InputInterface $input, OutputInterface $output)
    {
        $filename = $input->getArgument('filename');
        $token = $input->getArgument('token');

        $date = new \DateTime($input->getOption('date'));

        RemotePushNotificationManager::disable();

        $decoded = $this->hashids->decode($token);
        if (count($decoded) !== 1) {
            $this->io->caution(sprintf('Token "%s" could not be decoded', $token));
            return 1;
        }

        $taskGroupId = current($decoded);

        $taskGroup = $this->objectManager
            ->getRepository(TaskGroup::class)
            ->find($taskGroupId);

        if (!$taskGroup) {
            $this->io->caution(sprintf('TaskGroup #%d does not exist', $taskGroupId));
            return 1;
        }

        // Download file locally
        $tempDir = sys_get_temp_dir();
        $tempnam = tempnam($tempDir, 'coopcycle_task_import');

        if (false === file_put_contents($tempnam, $this->taskImportsFilesystem->read($filename))) {
            $this->io->caution('Could not write temp file');
            return 1;
        }

        try {

            $tasks = $this->spreadsheetParser->parse($tempnam, $date);

            foreach ($tasks as $task) {
                $violations = $this->validator->validate($task);
                if (count($violations) > 0) {
                    throw new \Exception($violations->get(0)->getMessage());
                }
            }

            $this->io->text(sprintf('Importing %d tasks…', count($tasks)));

        } catch (\Exception $e) {
            $this->socketIoManager->toAdmins('task_import:failure', [
                ['message' => $e->getMessage()]
            ]);
            unlink($tempnam);
            return 1;
        }

        foreach ($tasks as $task) {
            $task->setGroup($taskGroup);
            $this->objectManager->persist($task);
        }

        $this->objectManager->flush();

        $this->io->success(sprintf('Finished importing file %s', $filename));

        $this->socketIoManager->toAdmins('task_import:success', ['token' => $token]);

        unlink($tempnam);

        return 0;
    }
}
