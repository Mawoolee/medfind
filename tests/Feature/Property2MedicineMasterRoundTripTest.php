<?php

namespace Tests\Feature;

use App\Models\Medicine;
use App\Models\Pharmacy;
use App\Services\MedicineMasterService;
use Eris\Generators;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Support\PropertyTestCase;

final class Property2MedicineMasterRoundTripTest extends PropertyTestCase
{
    use RefreshDatabase;

    protected function shouldSeed(): bool
    {
        return false;
    }

    /** **Validates: Requirements 2.1, 2.2** */
    public function test_medicine_master_fields_round_trip_through_storage(): void
    {
        // Feature: pharmacy-medicine-batch-stock-management, Property 2: Medicine master round trip
        $pharmacy = Pharmacy::factory()->create();
        $service = app(MedicineMasterService::class);

        $this->forAll(
            Generators::elements([
                'Paracetamol',
                'Amoxicillin trihydrate',
                'Ácido acetilsalicílico',
                'Ιβουπροφαίνη',
                'インスリン グラルギン',
            ]),
            Generators::elements([null, 'MedFind', 'Farmácia São João', '製薬ブランド']),
            Generators::elements(['5 mg', '100 units/mL', '250 mg / 5 mL', '0.5% w/v']),
            Generators::elements([null, 'Analgesic', 'Anti-infective', '心血管薬']),
            Generators::elements(['Generic Pharma', 'Laboratórios Saúde', '製薬株式会社']),
            Generators::elements([false, true]),
            Generators::elements([false, true]),
        )->then(function (
            string $genericName,
            ?string $brandName,
            string $dosage,
            ?string $category,
            string $manufacturer,
            bool $requiresPrescription,
            bool $coldChainRequired,
        ) use ($pharmacy, $service): void {
            $aggregate = $service->createForPharmacy($pharmacy, [
                'medicine_name' => $genericName,
                'brand_name' => $brandName,
                'dosage' => $dosage,
                'category' => $category,
                'manufacturer' => $manufacturer,
                'requiresPrescription' => $requiresPrescription,
                'cold_chain_required' => $coldChainRequired,
            ], 0);

            $medicine = Medicine::query()->findOrFail($aggregate->medicine_id);

            self::assertSame($genericName, $medicine->medicine_name);
            self::assertSame($brandName, $medicine->brand_name);
            self::assertSame($dosage, $medicine->dosage);
            self::assertSame($category, $medicine->category);
            self::assertSame($manufacturer, $medicine->manufacturer);
            self::assertSame($requiresPrescription, $medicine->requiresPrescription);
            self::assertSame($coldChainRequired, $medicine->cold_chain_required);
        });
    }
}
