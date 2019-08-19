<?php

namespace AppBundle\ExpressionLanguage;

use AppBundle\Entity\Task;
use Carbon\Carbon;
use Doctrine\ORM\EntityRepository;
use Symfony\Component\ExpressionLanguage\ExpressionFunction;
use Symfony\Component\ExpressionLanguage\ExpressionFunctionProviderInterface;

class PickupExpressionLanguageProvider implements ExpressionFunctionProviderInterface
{
    public function getFunctions()
    {
        $compiler = function (Address $address, $zoneName) {
            // FIXME Need to test compilation
        };

        $evaluator = function ($arguments, $task) {

            $now = Carbon::now();

            if (isset($task->createdAt) && null !== $task->createdAt) {
                $now = Carbon::instance($task->createdAt);
            }

            $before = Carbon::instance($task->before);
            $diff = $before->diffInDays($now);

            if ($diff === 0) {
                $diff = $before->isSameDay($now) ? 0 : 1;
            }

            return $diff;
        };

        return array(
            new ExpressionFunction('diff_days', $compiler, $evaluator),
        );
    }
}
