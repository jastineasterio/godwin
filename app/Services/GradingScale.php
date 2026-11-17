<?php

namespace App\Services;

/**
 * Grading scale applied to percentage scores (0-100).
 *
 * FINDING: No official grading configuration exists in this codebase — there is
 * no `grading` config file, no settings rows, and no database table for
 * grade bands. The bands below are the de-facto scale previously hard-coded in
 * both ReportCardController and ResultsController (90 / 75 / 60 / 50), extracted
 * here so report cards and analytics share exactly one source of truth.
 *
 * This is NOT a newly invented official scale. It is the scale already in use,
 * now centralized. School leadership must confirm it against the official
 * grading policy before results are treated as official. If the school adopts
 * a different scale, this is the only file that needs to change.
 */
class GradingScale
{
    public const BANDS = [
        90 => 'A — Excellent',
        75 => 'B — Very Good',
        60 => 'C — Good',
        50 => 'D — Satisfactory',
        0 => 'E — Needs Support',
    ];

    /** Convert a percentage (0-100) into its grade band. */
    public static function grade(float|int $percentage): string
    {
        foreach (self::BANDS as $floor => $label) {
            if ((float) $percentage >= $floor) {
                return $label;
            }
        }

        return self::BANDS[0];
    }
}
