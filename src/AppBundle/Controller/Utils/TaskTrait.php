<?php

namespace AppBundle\Controller\Utils;

use AppBundle\Entity\Task;
use AppBundle\Form\TaskType;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;

trait TaskTrait
{
    protected function createDefaultTask(\DateTime $date)
    {
        $now = new \DateTime();

        $task = new Task();

        $doneAfter = clone $date;
        $doneBefore = clone $date;

        $doneAfter->setTime($now->format('H'), $now->format('i'));
        $doneBefore->setTime($now->format('H'), $now->format('i'));

        $doneAfter->modify('+1 hour');
        $doneBefore->modify('+1 hour 30 minutes');

        $task->setDoneAfter($doneAfter);
        $task->setDoneBefore($doneBefore);

        return $task;
    }

    protected function createTaskEditForm(Task $task)
    {
        return $this->get('form.factory')->createNamed('task_edit', TaskType::class, $task, [
            'can_edit_type' => false,
            'date_range' => true
        ]);
    }
}
