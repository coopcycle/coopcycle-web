<?php

namespace AppBundle\MessageHandler;

use AppBundle\Entity\Organization;
use AppBundle\Entity\Task\Group as TaskGroup;
use AppBundle\Entity\Task\ImportQueue as TaskImportQueue;
use AppBundle\Message\ImportTasks;
use AppBundle\Service\RemotePushNotificationManager;
use AppBundle\Service\LiveUpdates;
use AppBundle\Doctrine\EventSubscriber\TaskSubscriber\TaskListProvider;
use AppBundle\Spreadsheet\TaskSpreadsheetParser;
use Doctrine\DBAL\Exception\DriverException;
use Doctrine\ORM\EntityManagerInterface;
use League\Flysystem\Filesystem;
use Psr\Log\LoggerInterface;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;
use Symfony\Component\Validator\Validator\ValidatorInterface;

#[AsMessageHandler]
class ImportTasksHandler
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
        LoggerInterface $logger,
        private TaskListProvider $taskListProvider)
    {
        $this->objectManager = $objectManager;
        $this->taskImportsFilesystem = $taskImportsFilesystem;
        $this->spreadsheetParser = $spreadsheetParser;
        $this->validator = $validator;
        $this->liveUpdates = $liveUpdates;
        $this->logger = $logger;
    }

    /**
     * @param array<string,TaskGroup> $groups the groups made for this file so far
     */
    private function getOrCreateTaskGroup(string $groupName, array &$groups): TaskGroup
    {
        if (!array_key_exists($groupName, $groups)) {
            $taskGroup = new TaskGroup();
            $taskGroup->setName($groupName);

            $this->objectManager->persist($taskGroup);

            $groups[$groupName] = $taskGroup;
        }

        return $groups[$groupName];
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

            $result = $this->spreadsheetParser->parse($tempnam, [
                'date' => $message->getDate()
            ]);

            // This path fails the whole file on the first thing wrong with it, as it
            // always has: its callers, the API among them, report one error and no rows.
            // The import screen is the one that reports them row by row.
            if ($result->hasErrors()) {
                $errors = $result->getSortedErrors();
                $firstRow = reset($errors);

                throw new \Exception($firstRow[0] ?? 'Could not read the file');
            }

            $rows = $result->getData();

            $this->logger->info(sprintf('Importing %d tasks…', count($rows)));

            foreach ($rows as $row) {
                $violations = $this->validator->validate($row['task']);
                if (count($violations) > 0) {
                    throw new \Exception($violations->get(0)->getMessage());
                }
            }

        } catch (\Exception $e) {

            $this->logger->error(sprintf('Error importing file %s, "%s"', $message->getFilename(), $e->getMessage()));

            $this->liveUpdates->toDispatchers('task_import:failure', [
                'token' => $message->getToken(),
                'message' => $e->getMessage(),
            ]);
            $this->updateQueueStatus($message, 'failed', $e->getMessage());

            unlink($tempnam);
            return;
        }

        try {
            $taskGroup = null;
            // The parser leaves the groups to be made here, so that nothing is written
            // before the rows are known to be good
            $namedGroups = [];

            foreach ($rows as $row) {
                $task = $row['task'];

                if (!is_null($row['groupName'])) {
                    $task->setGroup($this->getOrCreateTaskGroup($row['groupName'], $namedGroups));
                }

                if (!is_null($row['assign'])) {
                    [ $user, $assignAt ] = $row['assign'];
                    // The task list (its date + courier) is the source of truth for the
                    // assignment; appendTask() also sets task.assignedTo.
                    $this->taskListProvider
                        ->getTaskListForUserAndDate($assignAt, $user)
                        ->appendTask($task);
                }

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

            $this->liveUpdates->toDispatchers('task_import:failure', [
                'token' => $message->getToken(),
                'message' => $e->getMessage(),
            ]);
            $this->updateQueueStatus($message, 'failed', $e->getMessage());

            unlink($tempnam);
            return;
        }

        $this->logger->info(sprintf('Finished importing file %s', $message->getFilename()));
        $this->liveUpdates->toDispatchers('task_import:success', [
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
