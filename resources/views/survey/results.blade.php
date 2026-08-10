@extends('layouts.app')

@section('title', 'ISO/IEC 25010 Evaluation Results')

@section('content')
<div class="container mx-auto px-4 py-8">

    {{-- Page header --}}
    <div class="flex items-center justify-between mb-6">
        <div>
            <p class="text-xs font-semibold uppercase tracking-widest text-[#9400D3]">ISO/IEC 25010 Software Quality</p>
            <h1 class="text-2xl font-extrabold text-[#191970]">Evaluation Survey Results</h1>
            <p class="text-sm text-gray-500 mt-0.5">{{ number_format($total) }} response{{ $total === 1 ? '' : 's' }} collected</p>
        </div>
        <div class="flex items-center gap-3">
            <a href="{{ route('survey.show') }}" target="_blank"
               class="text-sm bg-[#9400D3] text-white px-4 py-2 rounded-lg hover:bg-[#7a00b0] transition font-semibold">
                <i class="fas fa-external-link-alt mr-1"></i>Open Survey
            </a>
            <a href="{{ route('admin.dashboard') }}" class="text-sm text-[#9400D3] hover:text-[#7a00b0] font-medium">
                <i class="fas fa-arrow-left mr-1"></i>Back
            </a>
        </div>
    </div>

    {{-- Filter by respondent type --}}
    <form method="GET" action="{{ route('admin.survey.results') }}" class="mb-6 flex items-center gap-3">
        <label class="text-sm font-medium text-gray-600">Filter by respondent:</label>
        <select name="type" onchange="this.form.submit()"
            class="border border-gray-300 rounded-lg px-3 py-1.5 text-sm focus:outline-none focus:ring-2 focus:ring-[#9400D3]/30">
            <option value="">All respondents</option>
            <option value="consumer" {{ $respondentType === 'consumer' ? 'selected' : '' }}>Consumers</option>
            <option value="pharmacy" {{ $respondentType === 'pharmacy' ? 'selected' : '' }}>Pharmacy Staff</option>
            <option value="admin"    {{ $respondentType === 'admin'    ? 'selected' : '' }}>Administrators</option>
        </select>
        @if($respondentType)
            <a href="{{ route('admin.survey.results') }}" class="text-sm text-gray-400 hover:text-gray-600">
                <i class="fas fa-times mr-1"></i>Clear
            </a>
        @endif
    </form>

    @if($total === 0)
        <div class="bg-white rounded-2xl border border-gray-100 shadow-sm p-16 text-center">
            <i class="fas fa-clipboard-list text-5xl text-gray-200 mb-4 block"></i>
            <p class="text-gray-500 font-medium">No survey responses yet.</p>
            <p class="text-sm text-gray-400 mt-1">Share the survey link with respondents to begin collecting data.</p>
            <a href="{{ route('survey.show') }}" target="_blank"
               class="inline-block mt-4 text-sm text-[#9400D3] underline">Open Survey Form →</a>
        </div>
    @else

    {{-- Overall score cards --}}
    <div class="grid grid-cols-2 md:grid-cols-4 gap-4 mb-6">
        @php
            $cards = [
                ['label' => 'Functional Suitability', 'avg' => $fsAvg, 'icon' => 'fas fa-check-square', 'color' => 'text-blue-600',   'bg' => 'bg-blue-50'],
                ['label' => 'Usability',               'avg' => $usAvg, 'icon' => 'fas fa-hand-pointer','color' => 'text-[#9400D3]', 'bg' => 'bg-purple-50'],
                ['label' => 'Security',                'avg' => $seAvg, 'icon' => 'fas fa-shield-halved','color' => 'text-green-600', 'bg' => 'bg-green-50'],
                ['label' => 'Overall',                 'avg' => $overallAvg, 'icon' => 'fas fa-star',  'color' => 'text-amber-600',  'bg' => 'bg-amber-50'],
            ];
        @endphp
        @foreach($cards as $card)
            <div class="bg-white rounded-xl border border-gray-100 shadow-sm p-5 text-center">
                <div class="w-10 h-10 rounded-full {{ $card['bg'] }} flex items-center justify-center mx-auto mb-2">
                    <i class="{{ $card['icon'] }} {{ $card['color'] }}"></i>
                </div>
                <p class="text-xs text-gray-500 mb-1">{{ $card['label'] }}</p>
                <p class="text-3xl font-extrabold {{ $card['color'] }}">{{ number_format($card['avg'], 2) }}</p>
                <p class="text-xs text-gray-400">/ 5.00</p>
                @php
                    $pct  = $card['avg'] / 5 * 100;
                    $barColor = $card['avg'] >= 4 ? 'bg-green-500' : ($card['avg'] >= 3 ? 'bg-yellow-400' : 'bg-red-400');
                @endphp
                <div class="mt-2 h-1.5 rounded-full bg-gray-100 overflow-hidden">
                    <div class="h-full rounded-full {{ $barColor }}" style="width:{{ $pct }}%"></div>
                </div>
            </div>
        @endforeach
    </div>

    {{-- Respondent type breakdown --}}
    @if(!empty($typeBreakdown))
    <div class="bg-white rounded-xl border border-gray-100 shadow-sm p-5 mb-6">
        <h3 class="text-sm font-bold text-gray-700 mb-3">Respondent Breakdown</h3>
        <div class="flex flex-wrap gap-4">
            @foreach($typeBreakdown as $type => $cnt)
                <div class="flex items-center gap-2">
                    <span class="px-2 py-0.5 rounded-full text-xs font-semibold
                        {{ $type === 'consumer' ? 'bg-blue-100 text-blue-700' : ($type === 'pharmacy' ? 'bg-purple-100 text-purple-700' : 'bg-gray-100 text-gray-700') }}">
                        {{ ucfirst($type) }}
                    </span>
                    <span class="text-sm font-bold text-gray-700">{{ $cnt }}</span>
                    <span class="text-xs text-gray-400">{{ round($cnt / $total * 100) }}%</span>
                </div>
            @endforeach
        </div>
    </div>
    @endif

    {{-- Per-question averages table --}}
    <div class="bg-white rounded-xl border border-gray-100 shadow-sm overflow-hidden mb-6">
        <div class="px-6 py-4 border-b border-gray-100 bg-gray-50">
            <h3 class="font-bold text-gray-800">Per-Question Average Scores</h3>
            <p class="text-xs text-gray-500 mt-0.5">Scale: 1 (Strongly Disagree) → 5 (Strongly Agree)</p>
        </div>
        <table class="w-full">
            <thead>
                <tr class="bg-gray-50 border-b border-gray-100 text-left text-xs uppercase tracking-wide text-gray-500">
                    <th class="px-4 py-3 w-8">#</th>
                    <th class="px-4 py-3">Characteristic</th>
                    <th class="px-4 py-3">Statement</th>
                    <th class="px-4 py-3 text-center w-24">Average</th>
                    <th class="px-4 py-3 w-36">Bar</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-gray-50">
                @php $rowNum = 1; @endphp
                @foreach($questions as $characteristic => $items)
                    @foreach($items as $field => $statement)
                        @php
                            $avg = $averages[$field] ?? 0;
                            $barW = $avg / 5 * 100;
                            $barColor = $avg >= 4 ? 'bg-green-500' : ($avg >= 3 ? 'bg-yellow-400' : 'bg-red-400');
                            $charBadge = [
                                'Functional Suitability' => 'bg-blue-100 text-blue-700',
                                'Usability'              => 'bg-purple-100 text-purple-700',
                                'Security'               => 'bg-green-100 text-green-700',
                            ][$characteristic] ?? 'bg-gray-100 text-gray-600';
                        @endphp
                        <tr class="hover:bg-gray-50 transition">
                            <td class="px-4 py-3 text-xs text-gray-400">{{ $rowNum++ }}</td>
                            <td class="px-4 py-3">
                                <span class="px-2 py-0.5 rounded-full text-xs font-semibold whitespace-nowrap {{ $charBadge }}">
                                    {{ $characteristic }}
                                </span>
                            </td>
                            <td class="px-4 py-3 text-sm text-gray-700">{{ $statement }}</td>
                            <td class="px-4 py-3 text-center">
                                <span class="text-base font-bold {{ $avg >= 4 ? 'text-green-600' : ($avg >= 3 ? 'text-yellow-600' : 'text-red-500') }}">
                                    {{ number_format($avg, 2) }}
                                </span>
                            </td>
                            <td class="px-4 py-3">
                                <div class="h-2 bg-gray-100 rounded-full overflow-hidden">
                                    <div class="h-full rounded-full {{ $barColor }}" style="width:{{ $barW }}%"></div>
                                </div>
                            </td>
                        </tr>
                    @endforeach
                @endforeach
            </tbody>
        </table>
    </div>

    {{-- Individual responses --}}
    <div class="bg-white rounded-xl border border-gray-100 shadow-sm overflow-hidden">
        <div class="px-6 py-4 border-b border-gray-100 bg-gray-50 flex items-center justify-between">
            <h3 class="font-bold text-gray-800">Individual Responses</h3>
            <span class="text-xs text-gray-500">{{ $responses->total() }} total</span>
        </div>
        <div class="overflow-x-auto">
            <table class="w-full">
                <thead>
                    <tr class="border-b border-gray-100 text-left text-xs uppercase tracking-wide text-gray-500 bg-gray-50">
                        <th class="px-4 py-3">Respondent</th>
                        <th class="px-4 py-3">Type</th>
                        <th class="px-4 py-3 text-center">FS Avg</th>
                        <th class="px-4 py-3 text-center">US Avg</th>
                        <th class="px-4 py-3 text-center">SE Avg</th>
                        <th class="px-4 py-3 text-center">Overall</th>
                        <th class="px-4 py-3">Comments</th>
                        <th class="px-4 py-3 whitespace-nowrap">Date</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-50">
                    @forelse($responses as $resp)
                        <tr class="hover:bg-gray-50">
                            <td class="px-4 py-3 text-sm">
                                {{ $resp->respondent_name ?? ($resp->user?->name ?? '—') }}
                            </td>
                            <td class="px-4 py-3">
                                <span class="px-2 py-0.5 rounded-full text-xs font-semibold
                                    {{ $resp->respondent_type === 'consumer' ? 'bg-blue-100 text-blue-700' : ($resp->respondent_type === 'pharmacy' ? 'bg-purple-100 text-purple-700' : 'bg-gray-100 text-gray-700') }}">
                                    {{ ucfirst($resp->respondent_type) }}
                                </span>
                            </td>
                            <td class="px-4 py-3 text-center font-mono text-sm {{ $resp->fs_average >= 4 ? 'text-green-600' : ($resp->fs_average >= 3 ? 'text-yellow-600' : 'text-red-500') }}">
                                {{ number_format($resp->fs_average, 2) }}
                            </td>
                            <td class="px-4 py-3 text-center font-mono text-sm {{ $resp->us_average >= 4 ? 'text-green-600' : ($resp->us_average >= 3 ? 'text-yellow-600' : 'text-red-500') }}">
                                {{ number_format($resp->us_average, 2) }}
                            </td>
                            <td class="px-4 py-3 text-center font-mono text-sm {{ $resp->se_average >= 4 ? 'text-green-600' : ($resp->se_average >= 3 ? 'text-yellow-600' : 'text-red-500') }}">
                                {{ number_format($resp->se_average, 2) }}
                            </td>
                            <td class="px-4 py-3 text-center font-mono font-bold text-sm text-[#191970]">
                                {{ number_format($resp->overall_average, 2) }}
                            </td>
                            <td class="px-4 py-3 text-xs text-gray-500 max-w-xs truncate">
                                {{ $resp->comments ?? '—' }}
                            </td>
                            <td class="px-4 py-3 text-xs text-gray-400 whitespace-nowrap">
                                {{ $resp->created_at->format('M d, Y') }}
                            </td>
                        </tr>
                    @empty
                        <tr><td colspan="8" class="px-4 py-10 text-center text-gray-400">No responses yet.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        @if($responses->hasPages())
            <div class="px-4 py-3 border-t border-gray-100">
                {{ $responses->withQueryString()->links() }}
            </div>
        @endif
    </div>

    @endif {{-- end if $total > 0 --}}
</div>
@endsection
