<?php

namespace Tests\Feature;

use Illuminate\Support\Facades\File;
use Tests\TestCase;

final class PharmacyLocationPickerTest extends TestCase
{
    public function test_profile_edit_view_links_to_separate_location_page(): void
    {
        $source = File::get(resource_path('views/pharmacy/profile_edit.blade.php'));

        // The inline map and editable coordinate inputs are gone from the form.
        self::assertStringNotContainsString('id="profileLocationMap"', $source);
        self::assertStringNotContainsString('type="number"', $source);

        // A button/link takes the user to the separate location picker page.
        self::assertStringContainsString("route('pharmacy.profile.location')", $source);
        self::assertStringContainsString('Set Pharmacy Location', $source);

        // Coordinates are still submitted, now as hidden inputs.
        self::assertStringContainsString('type="hidden" name="latitude"', $source);
        self::assertStringContainsString('type="hidden" name="longitude"', $source);

        // The Location heading is preserved.
        self::assertStringContainsString('Location Coordinates', $source);
    }

    public function test_profile_location_page_uses_google_maps_location_editor(): void
    {
        $source = File::get(resource_path('views/pharmacy/location_edit.blade.php'));
        $sharedSource = File::get(public_path('js/medfind-google.js'));

        // The authenticated picker preserves its form controls and backend field names.
        self::assertStringContainsString('id="profileLocationMap"', $source);
        self::assertStringContainsString('id="addressSearch"', $source);
        self::assertStringContainsString('id="addressSearchBtn"', $source);
        self::assertStringContainsString('id="useMyLocationBtn"', $source);
        self::assertStringContainsString('Use my current location', $source);
        self::assertStringContainsString('Save Location', $source);
        self::assertStringContainsString('name="latitude"', $source);
        self::assertStringContainsString('name="longitude"', $source);
        self::assertStringContainsString('name="address"', $source);

        // Leaflet, OpenStreetMap, Nominatim, and the obsolete custom result list are removed here only.
        self::assertStringNotContainsString('unpkg.com/leaflet', $source);
        self::assertStringNotContainsString('tile.openstreetmap.org', $source);
        self::assertStringNotContainsString('nominatim.openstreetmap.org', $source);
        self::assertStringNotContainsString('id="searchResults"', $source);
        self::assertStringNotContainsString('L.map(', $source);
        self::assertStringNotContainsString('L.marker(', $source);

        // The shared editor provides the Google map, marker interactions, geocoding, and Places UI.
        self::assertStringContainsString('class PharmacyLocationEditor', $sharedSource);
        self::assertStringContainsString("'profileLocationMap'", $sharedSource);
        self::assertStringContainsString('findLocationPickerContainer', $sharedSource);
        self::assertStringContainsString('new google.maps.Map', $sharedSource);
        self::assertStringContainsString('new google.maps.Marker', $sharedSource);
        self::assertStringContainsString('draggable: true', $sharedSource);
        self::assertStringContainsString("this.marker.addListener('dragend'", $sharedSource);
        self::assertStringContainsString("this.map.addListener('click'", $sharedSource);
        self::assertStringContainsString('toFixed(6)', $sharedSource);
        self::assertStringContainsString('new google.maps.Geocoder', $sharedSource);
        self::assertStringContainsString("componentRestrictions: { country: 'ph' }", $sharedSource);
        self::assertStringContainsString('new google.maps.places.Autocomplete', $sharedSource);
        self::assertStringContainsString("fields: ['formatted_address', 'geometry']", $sharedSource);
        self::assertStringContainsString('navigator.geolocation.getCurrentPosition', $sharedSource);
        self::assertStringContainsString('Location not available', $sharedSource);
    }

    public function test_registration_details_view_links_to_separate_location_page(): void
    {
        $source = File::get(resource_path('views/auth/pharmacy/register-details.blade.php'));

        // No inline map or editable coordinate inputs on the details form.
        self::assertStringNotContainsString('id="registerLocationMap"', $source);
        self::assertStringNotContainsString('type="number"', $source);

        // A button/link to the separate location picker page.
        self::assertStringContainsString("route('register.pharmacy.location')", $source);
        self::assertStringContainsString('Set Pharmacy Location', $source);

        // Coordinates submitted as hidden inputs.
        self::assertStringContainsString('type="hidden" name="latitude"', $source);
        self::assertStringContainsString('type="hidden" name="longitude"', $source);
    }

    public function test_registration_location_page_uses_google_maps_location_editor(): void
    {
        $source = File::get(resource_path('views/auth/pharmacy/register-location.blade.php'));
        $sharedSource = File::get(public_path('js/medfind-google.js'));
        $guestLayout = File::get(resource_path('views/layouts/guest.blade.php'));

        // The registration picker keeps its container, controls, and backend field names.
        self::assertStringContainsString('id="registerLocationMap"', $source);
        self::assertStringContainsString('id="addressSearch"', $source);
        self::assertStringContainsString('id="addressSearchBtn"', $source);
        self::assertStringContainsString('Enter street address, city, or area', $source);
        self::assertStringContainsString('id="useMyLocationBtn"', $source);
        self::assertStringContainsString('Use my current location', $source);
        self::assertStringContainsString('id="geoNotice"', $source);
        self::assertStringContainsString('Save Location', $source);
        self::assertStringContainsString('name="latitude"', $source);
        self::assertStringContainsString('name="longitude"', $source);
        self::assertStringContainsString('name="address"', $source);
        self::assertStringContainsString("route('register.pharmacy.location.store')", $source);

        // Leaflet, OpenStreetMap, Nominatim, and the obsolete custom result list are gone.
        self::assertStringNotContainsString('unpkg.com/leaflet', $source);
        self::assertStringNotContainsString('tile.openstreetmap.org', $source);
        self::assertStringNotContainsString('nominatim.openstreetmap.org', $source);
        self::assertStringNotContainsString('id="searchResults"', $source);
        self::assertStringNotContainsString('L.map(', $source);
        self::assertStringNotContainsString('L.marker(', $source);
        self::assertStringNotContainsString('mapbox', $source);

        // The guest layout loads the shared editor and Google Maps API without a hardcoded key.
        self::assertStringContainsString(':google-maps="true"', $source);
        self::assertStringContainsString("asset('js/medfind-google.js", $guestLayout);
        self::assertStringContainsString('maps.googleapis.com/maps/api/js', $guestLayout);
        self::assertStringContainsString("env('GOOGLE_MAPS_API_KEY')", $guestLayout);
        self::assertStringContainsString('libraries=places', $guestLayout);
        self::assertStringContainsString('callback=initGoogleMaps', $guestLayout);

        // The shared bootstrap resolves the registration container.
        self::assertStringContainsString("'registerLocationMap'", $sharedSource);
    }

    /**
     * @return array<string, array{0: string}>
     */
    public static function adminLocationPickerViewProvider(): array
    {
        return [
            'admin add location' => ['views/admin/pharmacy-location.blade.php'],
            'admin edit location' => ['views/admin/pharmacy-location-edit.blade.php'],
        ];
    }

    #[\PHPUnit\Framework\Attributes\DataProvider('adminLocationPickerViewProvider')]
    public function test_admin_location_pages_use_google_maps_location_editor(string $viewPath): void
    {
        $source = File::get(resource_path($viewPath));

        // Shared Google editor container plus the untouched controls and backend fields.
        self::assertStringContainsString('id="adminLocationMap"', $source);
        self::assertStringContainsString('id="addressSearch"', $source);
        self::assertStringContainsString('id="addressSearchBtn"', $source);
        self::assertStringContainsString('id="useMyLocationBtn"', $source);
        self::assertStringContainsString('Use my current location', $source);
        self::assertStringContainsString('id="geoNotice"', $source);
        self::assertStringContainsString('Save Location', $source);
        self::assertStringContainsString('type="hidden" name="latitude"', $source);
        self::assertStringContainsString('type="hidden" name="longitude"', $source);
        self::assertStringContainsString('type="hidden" name="address"', $source);
        self::assertStringContainsString('@csrf', $source);

        // Validation feedback is surfaced on the page.
        self::assertStringContainsString("\$errors->hasAny(['latitude', 'longitude', 'address'])", $source);

        // Leaflet, OpenStreetMap, Nominatim, and the obsolete custom result list are gone.
        self::assertStringNotContainsString('unpkg.com/leaflet', $source);
        self::assertStringNotContainsString('tile.openstreetmap.org', $source);
        self::assertStringNotContainsString('nominatim.openstreetmap.org', $source);
        self::assertStringNotContainsString('id="searchResults"', $source);
        self::assertStringNotContainsString('L.map(', $source);
        self::assertStringNotContainsString('L.marker(', $source);
    }

    public function test_shared_bootstrap_supports_every_location_picker_container(): void
    {
        $sharedSource = File::get(public_path('js/medfind-google.js'));

        // One shared container list drives the bootstrap for all three pickers.
        self::assertStringContainsString('LOCATION_PICKER_CONTAINER_IDS', $sharedSource);
        self::assertStringContainsString("'profileLocationMap'", $sharedSource);
        self::assertStringContainsString("'adminLocationMap'", $sharedSource);
        self::assertStringContainsString("'registerLocationMap'", $sharedSource);
        self::assertStringContainsString('function findLocationPickerContainer()', $sharedSource);
        self::assertStringContainsString('function initializeLocationEditor(', $sharedSource);

        // Initialization stays idempotent and safe on pages without a map.
        self::assertStringContainsString("dataset.googleMapInitialized === 'true'", $sharedSource);
        self::assertStringContainsString('if (!consumerContainer && !locationEditorContainer)', $sharedSource);
    }
}
