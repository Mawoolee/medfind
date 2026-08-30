<?php

namespace App\Http\Controllers;

use App\Http\Resolvers\PharmacyInventoryRecordResolver;
use App\Models\InventoryAudit;
use App\Models\Pharmacy;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Http\Request;
use Throwable;

class AuditLogController extends Controller
{
    /**
     * Change filters accepted from the query string.
     */
    private const CHANGE_FILTERS = ['increase', 'decrease'];

    public function index(Request $request, PharmacyInventoryRecordResolver $resolver)
    {
        try {
            $pharmacy = $resolver->pharmacy($request->user());
        } catch (ModelNotFoundException) {
            return redirect()->back()->with('error', 'No pharmacy assigned.');
        }

        $q = trim((string) $request->query('q', ''));
        $from = $this->parseFilterDate($request->query('from'));
        $to = $this->parseFilterDate($request->query('to'));
        $change = (string) $request->query('change', '');

        if (! in_array($change, self::CHANGE_FILTERS, true)) {
            $change = '';
        }

        $query = $this->pharmacyScopedQuery($pharmacy)->with(['inventoryItem.medicine', 'user']);

        if ($q !== '') {
            $query->whereHas(
                'inventoryItem.medicine',
                fn (Builder $medicineQuery) => $medicineQuery->where('medicine_name', 'like', "%{$q}%")
            );
        }

        // Boundaries are built in the application timezone so a filtered day covers
        // exactly that local calendar day for every stored timestamp.
        if ($from !== null) {
            $query->where('created_at', '>=', $from->startOfDay());
        }

        if ($to !== null) {
            $query->where('created_at', '<=', $to->endOfDay());
        }

        if ($change === 'increase') {
            $query->whereColumn('after_quantity', '>', 'before_quantity');
        } elseif ($change === 'decrease') {
            $query->whereColumn('after_quantity', '<', 'before_quantity');
        }

        // created_at alone is not unique, so the primary key breaks ties and keeps
        // pagination stable: no row is repeated or skipped between pages.
        $audits = $query
            ->orderByDesc('created_at')
            ->orderByDesc('id')
            ->paginate(20)
            ->withQueryString();

        // Summary counts are pharmacy-wide and intentionally ignore the filters above.
        $totalCount = $this->pharmacyScopedQuery($pharmacy)->count();
        $increaseCount = $this->pharmacyScopedQuery($pharmacy)
            ->whereColumn('after_quantity', '>', 'before_quantity')
            ->count();
        $decreaseCount = $this->pharmacyScopedQuery($pharmacy)
            ->whereColumn('after_quantity', '<', 'before_quantity')
            ->count();

        return view('pharmacy.audit_log', [
            'pharmacy' => $pharmacy,
            'audits' => $audits,
            'q' => $q,
            'from' => $from?->toDateString() ?? '',
            'to' => $to?->toDateString() ?? '',
            'change' => $change,
            'totalCount' => $totalCount,
            'increaseCount' => $increaseCount,
            'decreaseCount' => $decreaseCount,
        ]);
    }

    /**
     * Audit rows are always constrained to inventory owned by the given pharmacy.
     *
     * @return Builder<InventoryAudit>
     */
    private function pharmacyScopedQuery(Pharmacy $pharmacy): Builder
    {
        return InventoryAudit::query()->whereHas(
            'inventoryItem',
            fn (Builder $itemQuery) => $itemQuery->where('pharmacy_id', $pharmacy->getKey())
        );
    }

    /**
     * Normalize a `Y-m-d` filter value into the application timezone, ignoring junk input.
     */
    private function parseFilterDate(mixed $value): ?CarbonImmutable
    {
        $value = trim((string) $value);

        if ($value === '') {
            return null;
        }

        try {
            return CarbonImmutable::createFromFormat('!Y-m-d', $value, config('app.timezone'));
        } catch (Throwable) {
            return null;
        }
    }
}
