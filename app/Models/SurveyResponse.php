<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SurveyResponse extends Model
{
    protected $fillable = [
        'user_id', 'respondent_type', 'respondent_name',
        // Functional Suitability
        'fs_completeness', 'fs_correctness', 'fs_appropriateness',
        // Usability
        'us_recognisability', 'us_learnability', 'us_operability',
        'us_error_protection', 'us_aesthetics',
        // Security
        'se_confidentiality', 'se_integrity', 'se_accountability',
        // Open-ended
        'comments',
    ];

    protected $casts = [
        'fs_completeness'    => 'integer',
        'fs_correctness'     => 'integer',
        'fs_appropriateness' => 'integer',
        'us_recognisability' => 'integer',
        'us_learnability'    => 'integer',
        'us_operability'     => 'integer',
        'us_error_protection'=> 'integer',
        'us_aesthetics'      => 'integer',
        'se_confidentiality' => 'integer',
        'se_integrity'       => 'integer',
        'se_accountability'  => 'integer',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    // ─────────────────────────────────────────────────────────────────────────
    // Computed averages per characteristic
    // ─────────────────────────────────────────────────────────────────────────

    /** Average Functional Suitability score for this response (1-5). */
    public function getFsAverageAttribute(): float
    {
        $scores = array_filter([
            $this->fs_completeness,
            $this->fs_correctness,
            $this->fs_appropriateness,
        ]);
        return count($scores) ? round(array_sum($scores) / count($scores), 2) : 0;
    }

    /** Average Usability score for this response (1-5). */
    public function getUsAverageAttribute(): float
    {
        $scores = array_filter([
            $this->us_recognisability,
            $this->us_learnability,
            $this->us_operability,
            $this->us_error_protection,
            $this->us_aesthetics,
        ]);
        return count($scores) ? round(array_sum($scores) / count($scores), 2) : 0;
    }

    /** Average Security score for this response (1-5). */
    public function getSeAverageAttribute(): float
    {
        $scores = array_filter([
            $this->se_confidentiality,
            $this->se_integrity,
            $this->se_accountability,
        ]);
        return count($scores) ? round(array_sum($scores) / count($scores), 2) : 0;
    }

    /** Overall average across all three characteristics. */
    public function getOverallAverageAttribute(): float
    {
        $avgs = array_filter([$this->fs_average, $this->us_average, $this->se_average]);
        return count($avgs) ? round(array_sum($avgs) / count($avgs), 2) : 0;
    }

    // ─────────────────────────────────────────────────────────────────────────
    // Static helpers for the results dashboard
    // ─────────────────────────────────────────────────────────────────────────

    /** Column names grouped by characteristic. */
    public static function questionColumns(): array
    {
        return [
            'Functional Suitability' => [
                'fs_completeness'    => 'The system provides all functions I need.',
                'fs_correctness'     => 'Search results are accurate and reliable.',
                'fs_appropriateness' => 'The system helps me find medicines efficiently.',
            ],
            'Usability' => [
                'us_recognisability'  => 'The purpose of the system is immediately clear.',
                'us_learnability'     => 'I learned how to use the system quickly.',
                'us_operability'      => 'The system is easy to navigate and use.',
                'us_error_protection' => 'The system prevents me from making mistakes.',
                'us_aesthetics'       => 'The interface is visually appealing and well-designed.',
            ],
            'Security' => [
                'se_confidentiality' => 'I trust that my personal / prescription data is kept private.',
                'se_integrity'       => 'The medicine stock information shown is accurate and trustworthy.',
                'se_accountability'  => 'The system clearly shows who updated or verified information.',
            ],
        ];
    }
}
