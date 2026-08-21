<?php

namespace AppBundle\Service\Shift\Compliance;

/**
 * Shipped legal-constraint templates for shift planning, keyed by template id.
 * A template bundles the working-time rules of a country and/or sector; the
 * instance admin picks one and can override any value (or blank a rule to
 * disable it) — see ShiftSettings. Violations warn, they never block.
 *
 * Rule keys (all numeric, null/absent = rule disabled):
 *  - maxDailyHours          max net working hours in a calendar day
 *  - maxWeeklyHours         max net working hours in an ISO week
 *  - maxAvgWeeklyHours      max average weekly hours over avgWeeklyHoursWindowWeeks
 *  - avgWeeklyHoursWindowWeeks  rolling window (in weeks) for the average
 *  - minDailyRestHours      min rest between the last shift of a day and the
 *                           first shift of the next day
 *  - minWeeklyRestHours     min continuous shift-free period each week
 *  - breakThresholdHours    daily work time above which a break is due
 *  - minBreakMinutes        min total break time on days above the threshold
 *  - maxConsecutiveDays     max consecutive days with at least one shift
 */
final class ConstraintTemplates
{
    /**
     * Convention collective nationale des transports routiers et activités
     * auxiliaires du transport (France, IDCC 16) + French labor code baseline:
     * 10h/day, 48h/week and 44h average over 12 weeks, 11h daily rest, 35h
     * weekly rest, 30 min break past 6h, at most 6 consecutive working days.
     */
    public const TEMPLATES = [
        'ccn_transport_fr' => [
            'country' => 'fr',
            'sector' => 'road_transport',
            'rules' => [
                'maxDailyHours' => 10.0,
                'maxWeeklyHours' => 48.0,
                'maxAvgWeeklyHours' => 44.0,
                'avgWeeklyHoursWindowWeeks' => 12,
                'minDailyRestHours' => 11.0,
                'minWeeklyRestHours' => 35.0,
                'breakThresholdHours' => 6.0,
                'minBreakMinutes' => 30,
                'maxConsecutiveDays' => 6,
            ],
        ],
    ];

    public const RULE_KEYS = [
        'maxDailyHours',
        'maxWeeklyHours',
        'maxAvgWeeklyHours',
        'avgWeeklyHoursWindowWeeks',
        'minDailyRestHours',
        'minWeeklyRestHours',
        'breakThresholdHours',
        'minBreakMinutes',
        'maxConsecutiveDays',
    ];

    public static function has(string $template): bool
    {
        return isset(self::TEMPLATES[$template]);
    }

    /**
     * @return array<string, float|int>
     */
    public static function rules(string $template): array
    {
        return self::TEMPLATES[$template]['rules'] ?? [];
    }
}
