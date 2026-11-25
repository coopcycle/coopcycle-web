<?php

declare(strict_types=1);

namespace AppBundle\Import;

use AppBundle\Doctrine\EventSubscriber\TaskSubscriber\TaskListProvider;
use AppBundle\Entity\ImportQueue;
use AppBundle\Entity\Task;
use AppBundle\Entity\Task\Group as TaskGroup;
use AppBundle\Spreadsheet\AbstractSpreadsheetParser;
use AppBundle\Spreadsheet\SpreadsheetParseResult;
use AppBundle\Spreadsheet\TaskSpreadsheetParser;
use Doctrine\ORM\EntityManagerInterface;
use League\Flysystem\Filesystem;
use Symfony\Component\Validator\Validator\ValidatorInterface;

/**
 * Saves the tasks a file holds, with their groups and their assignments.
 */
class TaskImportHandler implements ImportHandlerInterface
{
    public function __construct(
        private readonly EntityManagerInterface $entityManager,
        private readonly Filesystem $taskImportsFilesystem,
        private readonly TaskSpreadsheetParser $parser,
        private readonly ValidatorInterface $validator,
        private readonly TaskListProvider $taskListProvider,
    )
    {
    }

    public function getType(): string
    {
        return ImportQueue::TYPE_TASKS;
    }

    public function getParserType(): string
    {
        return 'task';
    }

    public function getFilesystem(): Filesystem
    {
        return $this->taskImportsFilesystem;
    }

    public function getParser(): AbstractSpreadsheetParser
    {
        return $this->parser;
    }

    /**
     * The file is read strictly here, unlike through the API: a dispatcher watching the
     * import is told about a column they can still fix, rather than finding tasks of the
     * wrong kind on the board.
     */
    public function defaultOptions(ImportQueue $queue): array
    {
        return ['strict_type_column' => true];
    }

    public function beforeSave(ImportQueue $queue): void
    {
    }

    public function afterSave(ImportQueue $queue): void
    {
    }

    public function onSaveFailed(ImportQueue $queue): void
    {
    }

    public function errorKey(string $kind): string
    {
        return sprintf('tasks.imports.error.%s', $kind);
    }

    public function save(ImportQueue $queue, SpreadsheetParseResult $result, ?int &$rowNumber): array
    {
        $dates = [];

        // Made here rather than while reading the file: nothing is written until the rows
        // are known to be good, and a rollback has to undo all of it
        $groups = [];
        $importGroup = null;

        foreach ($result->getData() as $rowNumber => $row) {

            /** @var Task $task */
            $task = $row['task'];

            $violations = $this->validator->validate($task);
            if (count($violations) > 0) {
                foreach ($violations as $violation) {
                    if ($violation->getInvalidValue() instanceof \Stringable) {
                        $errorMessage = sprintf('%s %s: %s', $violation->getPropertyPath(), $violation->getMessage(), (string) $violation->getInvalidValue());
                    } else {
                        $errorMessage = sprintf('%s %s', $violation->getPropertyPath(), $violation->getMessage());
                    }
                    $result->addErrorToRow($rowNumber, $errorMessage);
                }

                continue;
            }

            if (!is_null($row['groupName'])) {
                $task->setGroup($this->getOrCreateGroup($row['groupName'], $groups));
            } else {
                // The rows that name no group go into one named after the import, which is
                // how a dispatcher finds on the board what a file brought in
                if (is_null($importGroup)) {
                    $importGroup = $this->createGroup(sprintf('Import %s', date('d/m H:i')));
                }

                $task->setGroup($importGroup);
            }

            if (!is_null($queue->getOrganization())) {
                $task->setOrganization($queue->getOrganization());
            }

            // To find the tasks that came from a file
            $task->setMetadata('task_import_id', $queue->getId());

            if (!is_null($row['assign'])) {
                [ $user, $assignAt ] = $row['assign'];
                // The task list (its date + courier) is the source of truth for the
                // assignment; appendTask() also sets task.assignedTo.
                $this->taskListProvider
                    ->getTaskListForUserAndDate($assignAt, $user)
                    ->appendTask($task);
            }

            $this->entityManager->persist($task);

            foreach ([$task->getAfter(), $task->getBefore()] as $date) {
                if (!is_null($date)) {
                    $dates[$date->format('Y-m-d')] = true;
                }
            }

            // Flushed per row, so that a failure points at the row it happened on
            $this->entityManager->flush();
        }

        $dates = array_keys($dates);
        sort($dates);

        return $dates;
    }

    /**
     * @param array<string,TaskGroup> $groups the groups made for this file so far
     */
    private function getOrCreateGroup(string $name, array &$groups): TaskGroup
    {
        if (!array_key_exists($name, $groups)) {
            $groups[$name] = $this->createGroup($name);
        }

        return $groups[$name];
    }

    private function createGroup(string $name): TaskGroup
    {
        $group = new TaskGroup();
        $group->setName($name);

        $this->entityManager->persist($group);

        return $group;
    }
}
