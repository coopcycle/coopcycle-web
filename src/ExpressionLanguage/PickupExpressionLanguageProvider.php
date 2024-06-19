<?php

namespace AppBundle\ExpressionLanguage;

use AppBundle\Entity\Address;
use Carbon\Carbon;
use Doctrine\ORM\EntityRepository;
use Symfony\Component\ExpressionLanguage\ExpressionFunction;
use Symfony\Component\ExpressionLanguage\ExpressionFunctionProviderInterface;
use Symfony\Component\ExpressionLanguage\ExpressionLanguage;

class PickupExpressionLanguageProvider implements ExpressionFunctionProviderInterface
{
    public function getFunctions()
    {
        $daysCompiler = function (Address $address, $zoneName) {
            // FIXME Need to test compilation
        };

        $daysEvaluator = function ($arguments, $task, ?string $expression = null): int|float|bool {

            if (null === $expression) {
                @trigger_error('Not passing an expression as the 2nd argument is deprecated', E_USER_DEPRECATED);
            }

            $now = Carbon::now();

            if (isset($arguments['task']) && $arguments['task']->type !== '' && $arguments['task'] !== $task) {
                return null === $expression ? -1 : false;
            }

            if (isset($task->createdAt) && null !== $task->createdAt) {
                $now = Carbon::instance($task->createdAt);
            }

            $before = Carbon::instance($task->before);
            $diff = $before->diffInDays($now);

            if ($diff === 0) {
                $diff = $before->isSameDay($now) ? 0 : 1;
            }

            if (null === $expression) {
                return $diff;
            }

            $el = new ExpressionLanguage();

            return $el->evaluate("{$diff} {$expression}");
        };

        $hoursCompiler = function (Address $address, $zoneName) {
            // FIXME Need to test compilation
        };

        $hoursEvaluator = function ($arguments, $task, ?string $expression = null): float|bool {

            if (null === $expression) {
                @trigger_error('Not passing an expression as the 2nd argument is deprecated', E_USER_DEPRECATED);
            }

            $now = Carbon::now();

            if (isset($arguments['task']) && $arguments['task']->type !== '' && $arguments['task'] !== $task) {
                return null === $expression ? -1 : false;
            }

            if (null === $task->before) {
                return null === $expression ? -1 : false;
            }

            if (isset($task->createdAt) && null !== $task->createdAt) {
                $now = Carbon::instance($task->createdAt);
            }

            $before = Carbon::instance($task->before);
            $diff = $before->floatDiffInHours($now);

            if (null === $expression) {
                return $diff;
            }

            $el = new ExpressionLanguage();

            return $el->evaluate("{$diff} {$expression}");
        };

        $timeRangeLengthCompiler = function ($task, $unit) {
            // FIXME Need to test compilation
        };

        $timeRangeLengthEvaluator = function ($arguments, $task, $unit, $expression = null) {

            if (isset($arguments['task']) && $arguments['task']->type !== '' && $arguments['task'] !== $task) {
                return null === $expression ? -1 : false;
            }

            if (null === $task->after || null === $task->before) {
                return null === $expression ? -1 : false;
            }

            $after = Carbon::instance($task->after);
            $before = Carbon::instance($task->before);

            $diff = $before->floatDiffInHours($after);

            if (null === $expression) {
                return $diff;
            }

            $el = new ExpressionLanguage();

            return $el->evaluate("{$diff} {$expression}");
        };

        return array(
            new ExpressionFunction('diff_days', $daysCompiler, $daysEvaluator),
            new ExpressionFunction('diff_hours', $hoursCompiler, $hoursEvaluator),
            new ExpressionFunction('time_range_length', $timeRangeLengthCompiler, $timeRangeLengthEvaluator),
        );
    }
}
