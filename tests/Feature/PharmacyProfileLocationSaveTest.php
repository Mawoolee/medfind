<?php

namespace Tests\Feature;

use App\Models\Pharmacy;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\File;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

final class PharmacyProfileLocationSaveTest extends TestCase
{
    use RefreshDatabase;

    public function test_save_location_persists_coordinates_for_the_owner_pharmacy(): void
    {
        $owner = User::factory()->create(['role' => 'pharmacy']);
        $pharmacy = Pharmacy::factory()->withOwner($owner)->create([
            'latitude' => 13.1500000,
            'longitude' => 123.7500000,
            'pharmacyAddress' => 'Old Address, Legazpi City',
        ]);

        $this->actingAs($owner)
            ->post(route('pharmacy.profile.location.store'), [
                'latitude' => '14.599500',
                'longitude' => '120.984200',
                'address' => 'Rizal Park, Manila',
            ])
            ->assertRedirect(route('pharmacy.profile.edit'))
            ->assertSessionHas('success', 'Pharmacy location saved.');

        $pharmacy->refresh();
        $this->assertSame(14.5995, round((float) $pharmacy->latitude, 6));
        $this->assertSame(120.9842, round((float) $pharmacy->longitude, 6));

        // A non-empty existing address is never overwritten by the picker.
        $this->assertSame('Old Address, Legazpi City', $pharmacy->pharmacyAddress);
    }

    public function test_save_location_fills_an_empty_address_from_the_picker(): void
    {
        $owner = User::factory()->create(['role' => 'pharmacy']);
        $pharmacy = Pharmacy::factory()->withOwner($owner)->create([
            'latitude' => null,
            'longitude' => null,
            'pharmacyAddress' => '',
        ]);

        $this->actingAs($owner)
            ->post(route('pharmacy.profile.location.store'), [
                'latitude' => '13.143900',
                'longitude' => '123.723400',
                'address' => 'Legazpi City, Albay',
            ])
            ->assertRedirect(route('pharmacy.profile.edit'))
            ->assertSessionHas('success');

        $pharmacy->refresh();
        $this->assertSame(13.1439, round((float) $pharmacy->latitude, 6));
        $this->assertSame(123.7234, round((float) $pharmacy->longitude, 6));
        $this->assertSame('Legazpi City, Albay', $pharmacy->pharmacyAddress);
    }

    #[DataProvider('invalidCoordinatePayloads')]
    public function test_invalid_coordinates_fail_validation_without_persisting(array $payload, array $expectedErrors): void
    {
        $owner = User::factory()->create(['role' => 'pharmacy']);
        $pharmacy = Pharmacy::factory()->withOwner($owner)->create([
            'latitude' => 13.1500000,
            'longitude' => 123.7500000,
        ]);

        $this->actingAs($owner)
            ->from(route('pharmacy.profile.location'))
            ->post(route('pharmacy.profile.location.store'), $payload)
            ->assertRedirect(route('pharmacy.profile.location'))
            ->assertSessionHasErrors($expectedErrors)
            ->assertSessionMissing('success');

        $pharmacy->refresh();
        $this->assertSame(13.15, round((float) $pharmacy->latitude, 6));
        $this->assertSame(123.75, round((float) $pharmacy->longitude, 6));
    }

    public static function invalidCoordinatePayloads(): array
    {
        return [
            'missing both' => [[], ['latitude', 'longitude']],
            'empty strings' => [['latitude' => '', 'longitude' => ''], ['latitude', 'longitude']],
            'latitude out of range' => [['latitude' => '95.5', 'longitude' => '120.9842'], ['latitude']],
            'longitude out of range' => [['latitude' => '14.5995', 'longitude' => '181.2'], ['longitude']],
            'non numeric' => [['latitude' => 'abc', 'longitude' => 'def'], ['latitude', 'longitude']],
        ];
    }

    public function test_save_location_never_touches_another_pharmacys_record(): void
    {
        $owner = User::factory()->create(['role' => 'pharmacy']);
        Pharmacy::factory()->withOwner($owner)->create([
            'latitude' => 13.1500000,
            'longitude' => 123.7500000,
        ]);

        $otherOwner = User::factory()->create(['role' => 'pharmacy']);
        $otherPharmacy = Pharmacy::factory()->withOwner($otherOwner)->create([
            'latitude' => 10.3157000,
            'longitude' => 123.8854000,
            'pharmacyAddress' => 'Cebu City',
        ]);

        $this->actingAs($owner)
            ->post(route('pharmacy.profile.location.store'), [
                'latitude' => '14.599500',
                'longitude' => '120.984200',
                'address' => 'Manila',
            ])
            ->assertRedirect(route('pharmacy.profile.edit'));

        $otherPharmacy->refresh();
        $this->assertSame(10.3157, round((float) $otherPharmacy->latitude, 6));
        $this->assertSame(123.8854, round((float) $otherPharmacy->longitude, 6));
        $this->assertSame('Cebu City', $otherPharmacy->pharmacyAddress);
    }

    public function test_picker_page_surfaces_validation_errors_and_success_flash(): void
    {
        $source = File::get(resource_path('views/pharmacy/location_edit.blade.php'));

        self::assertStringContainsString("session('success')", $source);
        self::assertStringContainsString("\$errors->hasAny(['latitude', 'longitude', 'address'])", $source);
        self::assertStringContainsString('id="geoNotice"', $source);
    }

    public function test_location_editor_always_syncs_hidden_coordinate_inputs(): void
    {
        $source = File::get(public_path('js/medfind-google.js'));

        self::assertStringNotContainsString(
            "if (this.hasExistingLocation) {\n            this.updateCoordinateFields(",
            $source
        );
        self::assertStringContainsString(
            'this.updateCoordinateFields(startPosition.lat, startPosition.lng);',
            $source
        );
    }
}
