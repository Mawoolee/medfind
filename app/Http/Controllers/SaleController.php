<?php

namespace App\Http\Controllers;

use App\Domain\Inventory\BasicSaleService;
use App\Domain\Inventory\Exceptions\ForeignInventoryRecord;
use App\Domain\Inventory\Exceptions\SaleLineInsufficientStock;
use App\Domain\Inventory\InventoryAggregateQuery;
use App\Http\Requests\Inventory\RecordSaleRequest;
use App\Http\Resolvers\PharmacyInventoryRecordResolver;
use App\Models\InventoryItem;
use App\Models\Medicine;
use Carbon\CarbonImmutable;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

final class SaleController extends Controller
{
    private const DRAFT_SESSION_KEY = 'basic_sale.draft';

    public function create(
        Request $request,
        PharmacyInventoryRecordResolver $resolver,
        InventoryAggregateQuery $aggregateQuery,
        BasicSaleService $saleService,
    ): View {
        $user = $request->user();
        abort_if($user === null, 403);

        $pharmacy = $resolver->pharmacy($user);
        $inventoryQuery = InventoryItem::query()
            ->with('medicine')
            ->where('pharmacy_id', $pharmacy->getKey())
            ->orderBy(
                Medicine::query()
                    ->select('medicine_name')
                    ->whereColumn('medicines.id', 'inventory_items.medicine_id')
                    ->limit(1)
            );
        $aggregateQuery->withAvailableStock($inventoryQuery);
        $aggregateQuery->available($inventoryQuery);
        $inventory = $inventoryQuery->get();
        $draft = $request->session()->get(self::DRAFT_SESSION_KEY);
        $draftReference = is_array($draft) ? ($draft['reference'] ?? null) : null;
        $draftTimestamp = is_array($draft) ? ($draft['generated_at'] ?? null) : null;

        if (! is_string($draftReference) || ! ($draftTimestamp instanceof CarbonImmutable)) {
            $draftTimestamp = CarbonImmutable::now();
            $draftReference = $saleService->newSaleReference($draftTimestamp);
            $request->session()->put(self::DRAFT_SESSION_KEY, [
                'reference' => $draftReference,
                'generated_at' => $draftTimestamp,
            ]);
        }

        return view('pharmacy.sales.create', [
            'pharmacy' => $pharmacy,
            'inventory' => $inventory,
            'staff' => $user,
            'saleReference' => $draftReference,
            'serverTimestamp' => $draftTimestamp,
        ]);
    }

    public function store(RecordSaleRequest $request, BasicSaleService $saleService): RedirectResponse
    {
        $draft = $request->session()->get(self::DRAFT_SESSION_KEY);
        $draftReference = is_array($draft) ? ($draft['reference'] ?? null) : null;

        if (! is_string($draftReference) || trim($draftReference) === '') {
            $draftReference = $saleService->newSaleReference();
        }

        try {
            $result = $saleService->record(
                $request->pharmacy(),
                $request->user(),
                $request->saleItems(),
                $request->notes(),
                $draftReference,
            );
        } catch (SaleLineInsufficientStock $exception) {
            return redirect()->back()
                ->withErrors(["items.{$exception->lineIndex}.quantity" => $exception->getMessage()])
                ->withInput();
        } catch (ForeignInventoryRecord) {
            abort(404);
        }

        $request->session()->forget(self::DRAFT_SESSION_KEY);

        return redirect()
            ->route('pharmacy.sales.create')
            ->with('success', "Sale {$result->saleReference} recorded. Stock was deducted automatically by FEFO.")
            ->with('sale_reference', $result->saleReference);
    }
}
