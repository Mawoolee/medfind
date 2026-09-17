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
        self::assertStringContainsString("document.getElementById('profileLocationMap')", $sharedSource);
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

    public function test_registration_location_page_uses_map_and_address_search(): void
    {
        $source = File::get(resource_path('views/auth/pharmacy/register-location.blade.php'));

        self::assertStringContainsString('id="registerLocationMap"', $source);

        // Address search box (Nominatim).
        self::assertStringContainsString('id="addressSearch"', $source);
        self::assertStringContainsString('Enter street address, city, or area', $source);
        self::assertStringContainsString('nominatim.openstreetmap.org', $source);

        // Geolocation control.
        self::assertStringContainsString('id="useMyLocationBtn"', $source);
        self::assertStringContainsString('Use my current location', $source);
        self::assertStringContainsString('navigator.geolocation', $source);

        // Save Location submits coordinates.
        self::assertStringContainsString('Save Location', $source);
        self::assertStringContainsString('name="latitude"', $source);
        self::assertStringContainsString('name="longitude"', $source);

        self::assertStringContainsString('unpkg.com/leaflet@1.9.4', $source);
        self::assertStringContainsString('tile.openstreetmap.org', $source);
        self::assertStringNotContainsString('googleapis.com', $source);
        self::assertStringNotContainsString('mapbox', $source);
    }
}
