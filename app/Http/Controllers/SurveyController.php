<?php

namespace App\Http\Controllers;

use App\Models\SurveyResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class SurveyController extends Controller
{
    // ─────────────────────────────────────────────────────────────────────────
    // Public Survey
    // ─────────────────────────────────────────────────────────────────────────

    /** Show the survey form (accessible to anyone — no login required). */
    public function show()
    {
        $questions = SurveyResponse::questionColumns();
        return view('survey.form', compact('questions'));
    }

    /** Store a survey response. */
    public function store(Request $request)
    {
        $allCols = collect(SurveyResponse::questionColumns())->flatMap(fn($q) => array_keys($q))->toArray();

        $rules = ['respondent_type' => 'required|in:consumer,pharmacy,admin',
                  'respondent_name' => 'nullable|string|max:100',
                  'comments'        => 'nullable|string|max:2000'];

        foreach ($allCols as $col) {
            $rules[$col] = 'required|integer|min:1|max:5';
        }

        $data = $request->validate($rules);
        $data['user_id'] = auth()->id();

        SurveyResponse::create($data);

        return redirect()->route('survey.thankyou');
    }

    /** Thank-you page after submission. */
    public function thankyou()
    {
        return view('survey.thankyou');
    }

    // ─────────────────────────────────────────────────────────────────────────
    // Admin Results Dashboard
    // ─────────────────────────────────────────────────────────────────────────

    /** Admin view: aggregate results with per-question averages. */
    public function results(Request $request)
    {
        $respondentType = $request->query('type', '');

        $query = SurveyResponse::query();
        if (!empty($respondentType)) {
            $query->where('respondent_type', $respondentType);
        }

        $total     = (clone $query)->count();
        $responses = (clone $query)->latest()->paginate(15)->withQueryString();

        // Per-question averages across all (filtered) responses
        $allCols = collect(SurveyResponse::questionColumns())
            ->flatMap(fn($q) => array_keys($q))
            ->toArray();

        $avgSelects = array_map(fn($col) => "ROUND(AVG(CAST({$col} AS FLOAT)), 2) as avg_{$col}", $allCols);

        $averages = [];
        if ($total > 0) {
            $row = (clone $query)->selectRaw(implode(', ', $avgSelects))->first();
            if ($row) {
                foreach ($allCols as $col) {
                    $averages[$col] = (float) $row->{"avg_{$col}"};
                }
            }
        }

        // Characteristic-level averages
        $fsCols = array_keys(SurveyResponse::questionColumns()['Functional Suitability']);
        $usCols = array_keys(SurveyResponse::questionColumns()['Usability']);
        $seCols = array_keys(SurveyResponse::questionColumns()['Security']);

        $fsAvg = $total > 0 ? round(collect($fsCols)->map(fn($c) => $averages[$c] ?? 0)->avg(), 2) : 0;
        $usAvg = $total > 0 ? round(collect($usCols)->map(fn($c) => $averages[$c] ?? 0)->avg(), 2) : 0;
        $seAvg = $total > 0 ? round(collect($seCols)->map(fn($c) => $averages[$c] ?? 0)->avg(), 2) : 0;
        $overallAvg = $total > 0 ? round(($fsAvg + $usAvg + $seAvg) / 3, 2) : 0;

        // Score distribution per characteristic (count of 1-5 ratings)
        $distribution = [];
        foreach (['fs_completeness', 'us_operability', 'se_confidentiality'] as $col) {
            if ($total > 0) {
                $rows = (clone $query)
                    ->selectRaw("{$col} as rating, COUNT(*) as cnt")
                    ->whereNotNull($col)
                    ->groupBy($col)
                    ->orderBy($col)
                    ->get()
                    ->pluck('cnt', 'rating')
                    ->toArray();
                $distribution[$col] = $rows;
            }
        }

        // Respondent type breakdown
        $typeBreakdown = (clone $query)
            ->selectRaw('respondent_type, COUNT(*) as cnt')
            ->groupBy('respondent_type')
            ->pluck('cnt', 'respondent_type')
            ->toArray();

        $questions = SurveyResponse::questionColumns();

        return view('survey.results', compact(
            'responses', 'total', 'averages', 'questions',
            'fsAvg', 'usAvg', 'seAvg', 'overallAvg',
            'distribution', 'typeBreakdown', 'respondentType'
        ));
    }
}
