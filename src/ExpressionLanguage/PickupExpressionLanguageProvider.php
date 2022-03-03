<?php

namespace AppBundle\ExpressionLanguage;

use AppBundle\Entity\Address;
use Carbon\Carbon;
use Doctrine\ORM\EntityRepository;
use Symfony\Component\ExpressionLanguage\ExpressionFunction;
use Symfony\Component\ExpressionLanguage\ExpressionFunctionProviderInterface;

class PickupExpressionLanguageProvider implements ExpressionFunctionProviderInterface
{
    public function getFunctions()
    {
        $daysCompiler = function (Address $address, $zoneName) {
            // FIXME Need to test compilation
        };

        $daysEvaluator = function ($arguments, $task) {

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

        $hoursCompiler = function (Address $address, $zoneName) {
            // FIXME Need to test compilation
        };

        $hoursEvaluator = function ($arguments, $task) {

            $now = Carbon::now();

            if (isset($task->createdAt) && null !== $task->createdAt) {
                $now = Carbon::instance($task->createdAt);
            }

            $before = Carbon::instance($task->before);

            return $before->floatDiffInHours($now);
        };

        $timeRangeLengthCompiler = function ($task, $unit) {
            // FIXME Need to test compilation
        };

        $timeRangeLengthEvaluator = function ($arguments, $task, $unit) {

            $after = Carbon::instance($task->after);
            $before = Carbon::instance($task->before);

            return $before->floatDiffInHours($after);
        };

        return array(
            new ExpressionFunction('diff_days', $daysCompiler, $daysEvaluator),
            new ExpressionFunction('diff_hours', $hoursCompiler, $hoursEvaluator),
            new ExpressionFunction('time_range_length', $timeRangeLengthCompiler, $timeRangeLengthEvaluator),
        );
    }
}
