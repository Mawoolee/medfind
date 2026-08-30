<?php

namespace Tests\Feature;

use App\Models\InventoryAudit;
use App\Models\InventoryItem;
use App\Models\Medicine;
use App\Models\Pharmacy;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Feature tests for the pharmacy inventory audit log listing.
 */
class PharmacyAuditLogTest extends TestCase
{
    use RefreshDatabase;

    public function test_staff_user_scoped_by_pharmacy_id_can_view_the_audit_log(): void
    {
        [, $pharmacy] = $this->makeOwnerAndPharmacy();
        $staff = User::factory()->create(['role' => 'pharmacy_operator', 'pharmacy_id' => $pharmacy->id]);
        $item = $this->item($pharmacy, 'Amoxicillin');
        $this->audit($item, 10, 4);

        $response = $this->actingAs($staff)->get(route('pharmacy.audit-log'));

        $response->assertOk()->assertSee('Amoxicillin');
        $this->assertSame($pharmacy->id, $response->viewData('pharmacy')->id);
        $this->assertSame(1, $response->viewData('audits')->total());
    }

    public function test_audit_rows_from_other_pharmacies_are_never_listed(): void
    {
        [$owner, $pharmacy] = $this->makeOwnerAndPharmacy();
        [, $otherPharmacy] = $this->makeOwnerAndPharmacy();

        $ownAudit = $this->audit($this->item($pharmacy, 'Own Medicine'), 5, 9);
        $foreignAudit = $this->audit($this->item($otherPharmacy, 'Foreign Medicine'), 5, 1);

        $response = $this->actingAs($owner)->get(route('pharmacy.audit-log'));

        $response->assertOk()->assertSee('Own Medicine')->assertDontSee('Foreign Medicine');
        $this->assertSame([$ownAudit->id], $response->viewData('audits')->pluck('id')->all());
        $this->assertSame(1, $response->viewData('totalCount'));
        $this->assertDatabaseHas('inventory_audits', ['id' => $foreignAudit->id]);
    }

    public function test_staff_user_of_one_pharmacy_cannot_see_another_pharmacys_audit_rows(): void
    {
        [, $pharmacy] = $this->makeOwnerAndPharmacy();
        [, $otherPharmacy] = $this->makeOwnerAndPharmacy();
        $staff = User::factory()->create(['role' => 'pharmacy_operator', 'pharmacy_id' => $pharmacy->id]);

        $this->audit($this->item($otherPharmacy, 'Foreign Medicine'), 8, 2);

        $response = $this->actingAs($staff)->get(route('pharmacy.audit-log'));

        $response->assertOk()->assertDontSee('Foreign Medicine');
        $this->assertSame(0, $response->viewData('audits')->total());
    }

    public function test_pagination_is_deterministic_when_timestamps_are_identical(): void
    {
        [$owner, $pharmacy] = $this->makeOwnerAndPharmacy();
        $item = $this->item($pharmacy, 'Paracetamol');
        $sameMoment = CarbonImmutable::parse('2025-05-05 08:00:00', config('app.timezone'));

        $ids = [];
        for ($i = 0; $i < 25; $i++) {
            $ids[] = $this->audit($item, $i, $i + 1, $sameMoment)->id;
        }

        rsort($ids);

        $firstPage = $this->actingAs($owner)->get(route('pharmacy.audit-log'))
            ->assertOk()
            ->viewData('audits');
        $secondPage = $this->actingAs($owner)->get(route('pharmacy.audit-log', ['page' => 2]))
            ->assertOk()
            ->viewData('audits');

        $firstPageIds = $firstPage->pluck('id')->all();
        $secondPageIds = $secondPage->pluck('id')->all();
        $paginated = array_merge($firstPageIds, $secondPageIds);

        $this->assertCount(20, $firstPageIds);
        $this->assertCount(5, $secondPageIds);
        $this->assertSame($ids, $paginated, 'Rows sharing a timestamp must page newest-id-first without gaps.');
        $this->assertSame($paginated, array_values(array_unique($paginated)), 'No row may appear on two pages.');
    }

    public function test_increase_filter_returns_only_rows_where_available_stock_grew(): void
    {
        [$owner, $pharmacy] = $this->makeOwnerAndPharmacy();
        $item = $this->item($pharmacy, 'Cetirizine');
        $increase = $this->audit($item, 4, 12);
        $this->audit($item, 12, 3);
        $this->audit($item, 7, 7);

        $audits = $this->actingAs($owner)
            ->get(route('pharmacy.audit-log', ['change' => 'increase']))
            ->assertOk()
            ->viewData('audits');

        $this->assertSame([$increase->id], $audits->pluck('id')->all());
    }

    public function test_decrease_filter_returns_only_rows_where_available_stock_fell(): void
    {
        [$owner, $pharmacy] = $this->makeOwnerAndPharmacy();
        $item = $this->item($pharmacy, 'Loperamide');
        $this->audit($item, 4, 12);
        $decrease = $this->audit($item, 12, 3);
        $this->audit($item, 7, 7);

        $audits = $this->actingAs($owner)
            ->get(route('pharmacy.audit-log', ['change' => 'decrease']))
            ->assertOk()
            ->viewData('audits');

        $this->assertSame([$decrease->id], $audits->pluck('id')->all());
    }

    public function test_summary_cards_stay_pharmacy_wide_while_the_table_is_filtered(): void
    {
        [$owner, $pharmacy] = $this->makeOwnerAndPharmacy();
        $item = $this->item($pharmacy, 'Ibuprofen');
        $this->audit($item, 4, 12);
        $this->audit($item, 12, 3);
        $this->audit($item, 7, 7);

        $response = $this->actingAs($owner)
            ->get(route('pharmacy.audit-log', ['change' => 'increase']))
            ->assertOk();

        $this->assertSame(1, $response->viewData('audits')->total());
        $this->assertSame(3, $response->viewData('totalCount'));
        $this->assertSame(1, $response->viewData('increaseCount'));
        $this->assertSame(1, $response->viewData('decreaseCount'));
        $response->assertSee('Pharmacy-wide totals');
    }

    public function test_date_filters_cover_the_whole_local_calendar_day(): void
    {
        [$owner, $pharmacy] = $this->makeOwnerAndPharmacy();
        $item = $this->item($pharmacy, 'Metformin');
        $timezone = config('app.timezone');

        $lateNight = $this->audit($item, 10, 5, CarbonImmutable::parse('2025-03-10 23:45:00', $timezone));
        $earlyMorning = $this->audit($item, 5, 20, CarbonImmutable::parse('2025-03-10 00:05:00', $timezone));
        $nextDay = $this->audit($item, 20, 15, CarbonImmutable::parse('2025-03-11 00:10:00', $timezone));

        $audits = $this->actingAs($owner)
            ->get(route('pharmacy.audit-log', ['from' => '2025-03-10', 'to' => '2025-03-10']))
            ->assertOk()
            ->viewData('audits');

        $this->assertEqualsCanonicalizing(
            [$lateNight->id, $earlyMorning->id],
            $audits->pluck('id')->all()
        );
        $this->assertNotContains($nextDay->id, $audits->pluck('id')->all());
    }

    public function test_page_states_that_quantities_are_available_stock_excluding_expired_batches(): void
    {
        [$owner, $pharmacy] = $this->makeOwnerAndPharmacy();
        $this->audit($this->item($pharmacy, 'Salbutamol'), 10, 4);

        $this->actingAs($owner)
            ->get(route('pharmacy.audit-log'))
            ->assertOk()
            ->assertSee('available stock')
            ->assertSee('Available Before')
            ->assertSee('Available After')
            ->assertSee('Expired batches excluded');
    }

    /**
     * @return array{0: User, 1: Pharmacy}
     */
    private function makeOwnerAndPharmacy(): array
    {
        $owner = User::factory()->create(['role' => 'pharmacy']);
        $pharmacy = Pharmacy::factory()->withOwner($owner)->create();
        $owner->update(['pharmacy_id' => $pharmacy->id]);

        return [$owner, $pharmacy];
    }

    private function item(Pharmacy $pharmacy, string $medicineName): InventoryItem
    {
        return InventoryItem::factory()->create([
            'pharmacy_id' => $pharmacy->id,
            'medicine_id' => Medicine::factory()->create(['medicine_name' => $medicineName])->id,
        ]);
    }

    private function audit(
        InventoryItem $item,
        int $before,
        int $after,
        ?CarbonImmutable $createdAt = null,
    ): InventoryAudit {
        $audit = new InventoryAudit([
            'inventory_item_id' => $item->id,
            'before_quantity' => $before,
            'after_quantity' => $after,
            'notes' => 'Test entry',
        ]);

        if ($createdAt !== null) {
            $audit->forceFill(['created_at' => $createdAt, 'updated_at' => $createdAt]);
        }

        $audit->save();

        return $audit;
    }
}
