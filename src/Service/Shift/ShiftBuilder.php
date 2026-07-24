<?php

namespace AppBundle\Service\Shift;

/**
 * Turns an hourly "couriers required" profile for a single day into a minimal set
 * of shift blocks.
 *
 * The core idea is a staircase decomposition: matching each up-step in demand to a
 * later down-step yields nested/staggered intervals, each covered by one courier —
 * exactly how a human-made rota staggers start & end times to hug a demand ramp,
 * rather than parking everyone on the peak block.
 */
final class ShiftBuilder
{
    /**
     * @param array<int, int> $needByHour couriers required, keyed by hour (0-23)
     * @return array<int, array{start: int, end: int, slots: int}>
     *         shift blocks with integer hour boundaries
     */
    public function buildDay(
        array $needByHour,
        int $openHour,
        int $closeHour,
        int $minHours,
        int $maxHours
    ): array {
        $blocks = $this->decompose($needByHour, $openHour, $closeHour);

        $blocks = array_map(
            fn (array $b) => $this->enforceMinLength($b, $openHour, $closeHour, $minHours),
            $blocks
        );

        $blocks = $this->enforceMaxLength($blocks, $maxHours);

        return $this->mergeIdentical($blocks);
    }

    /**
     * Staircase decomposition into unit (single-courier) intervals.
     *
     * @return array<int, array{start: int, end: int}>
     */
    private function decompose(array $needByHour, int $openHour, int $closeHour): array
    {
        $blocks = [];
        $openStarts = [];
        $prev = 0;

        for ($hour = $openHour; $hour <= $closeHour; $hour++) {
            // Force demand back to 0 at the closing boundary so every block is closed
            $current = $hour < $closeHour ? max(0, $needByHour[$hour] ?? 0) : 0;

            if ($current > $prev) {
                for ($i = 0; $i < $current - $prev; $i++) {
                    $openStarts[] = $hour;
                }
            } elseif ($current < $prev) {
                for ($i = 0; $i < $prev - $current; $i++) {
                    $blocks[] = ['start' => array_pop($openStarts), 'end' => $hour];
                }
            }

            $prev = $current;
        }

        return $blocks;
    }

    /**
     * @param array{start: int, end: int} $block
     * @return array{start: int, end: int}
     */
    private function enforceMinLength(array $block, int $openHour, int $closeHour, int $minHours): array
    {
        $length = $block['end'] - $block['start'];

        if ($length >= $minHours) {
            return $block;
        }

        // Grow the block symmetrically to the minimum length, staying within
        // operating hours (a short peak block becomes a usable shift)
        $padding = $minHours - $length;
        $start = max($openHour, $block['start'] - intdiv($padding, 2));
        $end = min($closeHour, $start + $minHours);
        $start = max($openHour, $end - $minHours);

        return ['start' => $start, 'end' => $end];
    }

    /**
     * @param array<int, array{start: int, end: int}> $blocks
     * @return array<int, array{start: int, end: int}>
     */
    private function enforceMaxLength(array $blocks, int $maxHours): array
    {
        $result = [];

        foreach ($blocks as $block) {
            $length = $block['end'] - $block['start'];

            if ($length <= $maxHours) {
                $result[] = $block;
                continue;
            }

            $pieces = (int) ceil($length / $maxHours);
            $pieceLength = (int) ceil($length / $pieces);

            for ($start = $block['start']; $start < $block['end']; $start += $pieceLength) {
                $result[] = ['start' => $start, 'end' => min($block['end'], $start + $pieceLength)];
            }
        }

        return $result;
    }

    /**
     * Collapse identical intervals into a single block with slots > 1.
     *
     * @param array<int, array{start: int, end: int}> $blocks
     * @return array<int, array{start: int, end: int, slots: int}>
     */
    private function mergeIdentical(array $blocks): array
    {
        $merged = [];

        foreach ($blocks as $block) {
            $key = $block['start'] . '-' . $block['end'];

            if (!isset($merged[$key])) {
                $merged[$key] = ['start' => $block['start'], 'end' => $block['end'], 'slots' => 0];
            }

            $merged[$key]['slots']++;
        }

        $result = array_values($merged);

        usort($result, fn ($a, $b) => [$a['start'], $a['end']] <=> [$b['start'], $b['end']]);

        return $result;
    }
}
