<?php

namespace AppBundle\MessageHandler;

use AppBundle\Entity\Organization;
use AppBundle\Entity\Task\Group as TaskGroup;
use AppBundle\Entity\Task\ImportQueue as TaskImportQueue;
use AppBundle\Message\ImportTasks;
use AppBundle\Service\RemotePushNotificationManager;
use AppBundle\Service\LiveUpdates;
use AppBundle\Spreadsheet\TaskSpreadsheetParser;
use Doctrine\DBAL\Exception\DriverException;
use Doctrine\ORM\EntityManagerInterface;
use League\Flysystem\Filesystem;
use Psr\Log\LoggerInterface;
use Symfony\Component\Messenger\Handler\MessageHandlerInterface;
use Symfony\Component\Validator\Validator\ValidatorInterface;

class ImportTasksHandler implements MessageHandlerInterface
{
    private $objectManager;
    private $taskImportsFilesystem;
    private $spreadsheetParser;
    private $validator;
    private $liveUpdates;
    private $logger;

    public function __construct(
        EntityManagerInterface $objectManager,
        Filesystem $taskImportsFilesystem,
        TaskSpreadsheetParser $spreadsheetParser,
        ValidatorInterface $validator,
        LiveUpdates $liveUpdates,
        LoggerInterface $logger)
    {
        $this->objectManager = $objectManager;
        $this->taskImportsFilesystem = $taskImportsFilesystem;
        $this->spreadsheetParser = $spreadsheetParser;
        $this->validator = $validator;
        $this->liveUpdates = $liveUpdates;
        $this->logger = $logger;
    }

    public function __invoke(ImportTasks $message)
    {
        RemotePushNotificationManager::disable();

        // Download file locally
        $tempDir = sys_get_temp_dir();
        $tempnam = tempnam($tempDir, 'coopcycle_task_import');

        if (false === file_put_contents($tempnam, $this->taskImportsFilesystem->read($message->getFilename()))) {
            $this->logger->error('Could not write temp file');
            return;
        }

        $this->updateQueueStatus($message, 'started');

        try {

            $tasks = $this->spreadsheetParser->parse($tempnam, [
                'date' => $message->getDate()
            ]);

            $this->logger->info(sprintf('Importing %d tasks…', count($tasks)));

            foreach ($tasks as $task) {
                $violations = $this->validator->validate($task);
                if (count($violations) > 0) {
                    throw new \Exception($violations->get(0)->getMessage());
                }
            }

        } catch (\Exception $e) {

            $this->logger->error(sprintf('Error importing file %s, "%s"', $message->getFilename(), $e->getMessage()));

            $this->liveUpdates->toAdmins('task_import:failure', [
                'token' => $message->getToken(),
                'message' => $e->getMessage(),
            ]);
            $this->updateQueueStatus($message, 'failed', $e->getMessage());

            unlink($tempnam);
            return;
        }

        try {
            $taskGroup = null;

            foreach ($tasks as $task) {
                if (null === $task->getGroup()) {
                    // if a group was not provided for the task
                    // we add it to the default group for Imports
                    if (null === $taskGroup) {
                        $taskGroup = new TaskGroup();
                        $taskGroup->setName(sprintf('Import %s', date('d/m H:i')));

                        $this->objectManager->persist($taskGroup);
                    }
                    $task->setGroup($taskGroup);
                }

                if (null !== $message->getOrgId()) {
                    $organization = $this->objectManager
                        ->getRepository(Organization::class)
                        ->find($message->getOrgId());
                    if (null !== $organization) {
                        $task->setOrganization($organization);
                    }
                }

                $this->objectManager->persist($task);
            }
            $this->objectManager->flush();

        } catch (DriverException $e) {

            $this->logger->error(sprintf('Error importing file %s, "%s"', $message->getFilename(), $e->getMessage()));

            $this->liveUpdates->toAdmins('task_import:failure', [
                'token' => $message->getToken(),
                'message' => $e->getMessage(),
            ]);
            $this->updateQueueStatus($message, 'failed', $e->getMessage());

            unlink($tempnam);
            return;
        }

        $this->logger->info(sprintf('Finished importing file %s', $message->getFilename()));
        $this->liveUpdates->toAdmins('task_import:success', [
            'token' => $message->getToken()
        ]);
        $this->updateQueueStatus($message, 'completed');

        unlink($tempnam);
    }

    private function updateQueueStatus(ImportTasks $message, string $status, $error = null)
    {
        if (null !== $message->getQueueId()) {

            $taskImportQueue = $this->objectManager
                ->getRepository(TaskImportQueue::class)
                ->find($message->getQueueId());

            if (null !== $taskImportQueue) {
                $taskImportQueue->setStatus($status);
                if ('started' === $status) {
                    $taskImportQueue->setStartedAt(new \DateTime());
                } else {
                    $taskImportQueue->setFinishedAt(new \DateTime());
                }
                if (null !== $error) {
                    $taskImportQueue->setError($error);
                }
                $this->objectManager->flush();
            }
        }
    }
}
