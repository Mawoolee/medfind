<?php

namespace Tests\Feature\Auth;

use App\Models\Pharmacy;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class RegistrationTest extends TestCase
{
    use RefreshDatabase;

    public function test_registration_screen_can_be_rendered(): void
    {
        $response = $this->get('/register');

        $response->assertStatus(200);
    }

    public function test_registration_screen_does_not_contain_role_toggle(): void
    {
        $response = $this->get('/register');

        $response->assertStatus(200);
        $response->assertDontSee('Pharmacy Owner card', false);
        $response->assertDontSee("role = 'pharmacy'", false);
        $response->assertDontSee('name="pharmacy_name"', false);
        // The consumer page should link to the dedicated pharmacy registration.
        $response->assertSee(route('register.pharmacy'), false);
    }

    public function test_new_consumers_can_register(): void
    {
        $response = $this->post('/register', [
            'name' => 'Test User',
            'email' => 'test@example.com',
            'password' => 'password',
            'password_confirmation' => 'password',
        ]);

        $this->assertAuthenticated();
        $response->assertRedirect(route('home'));

        $user = User::where('email', 'test@example.com')->first();
        $this->assertNotNull($user);
        $this->assertSame('consumer', $user->role);
        $this->assertDatabaseCount('pharmacies', 0);
    }

    public function test_pharmacy_registration_step_one_can_be_rendered(): void
    {
        $response = $this->get(route('register.pharmacy'));

        $response->assertStatus(200);
    }

    public function test_pharmacy_step_one_validates_and_stores_session_without_creating_user(): void
    {
        $response = $this->post(route('register.pharmacy.account'), [
            'name' => 'Pharma Owner',
            'email' => 'owner@example.com',
            'password' => 'password',
            'password_confirmation' => 'password',
        ]);

        $response->assertRedirect(route('register.pharmacy.details'));
        $response->assertSessionHas('pharmacy_registration.account');

        // No user should be created yet.
        $this->assertGuest();
        $this->assertDatabaseMissing('users', ['email' => 'owner@example.com']);
    }

    public function test_pharmacy_step_one_enforces_email_uniqueness(): void
    {
        User::create([
            'name' => 'Existing',
            'email' => 'taken@example.com',
            'password' => Hash::make('password'),
            'role' => 'consumer',
        ]);

        $response = $this->post(route('register.pharmacy.account'), [
            'name' => 'Pharma Owner',
            'email' => 'taken@example.com',
            'password' => 'password',
            'password_confirmation' => 'password',
        ]);

        $response->assertSessionHasErrors('email');
        $response->assertSessionMissing('pharmacy_registration.account');
    }

    public function test_pharmacy_step_two_redirects_to_step_one_without_session(): void
    {
        $response = $this->get(route('register.pharmacy.details'));

        $response->assertRedirect(route('register.pharmacy'));
    }

    public function test_pharmacy_step_two_links_to_location_page_without_inline_map(): void
    {
        // Seed step 1 session data so step 2 renders.
        $this->post(route('register.pharmacy.account'), [
            'name' => 'Pharma Owner',
            'email' => 'owner@example.com',
            'password' => 'password',
            'password_confirmation' => 'password',
        ])->assertRedirect(route('register.pharmacy.details'));

        $response = $this->get(route('register.pharmacy.details'));

        $response->assertStatus(200);
        // No inline map on the details form.
        $response->assertDontSee('id="registerLocationMap"', false);
        // Button/link to the separate location picker page.
        $response->assertSee('Set Pharmacy Location', false);
        $response->assertSee(route('register.pharmacy.location'), false);
        // Coordinates submitted as hidden inputs under the expected names.
        $response->assertSee('type="hidden" name="latitude"', false);
        $response->assertSee('type="hidden" name="longitude"', false);
    }

    public function test_pharmacy_location_page_redirects_without_step_one_session(): void
    {
        $response = $this->get(route('register.pharmacy.location'));

        $response->assertRedirect(route('register.pharmacy'));
    }

    public function test_pharmacy_location_page_renders_map_and_address_search(): void
    {
        $this->post(route('register.pharmacy.account'), [
            'name' => 'Pharma Owner',
            'email' => 'owner@example.com',
            'password' => 'password',
            'password_confirmation' => 'password',
        ])->assertRedirect(route('register.pharmacy.details'));

        $response = $this->get(route('register.pharmacy.location'));

        $response->assertStatus(200);
        $response->assertSee('id="registerLocationMap"', false);
        $response->assertSee('id="addressSearch"', false);
        $response->assertSee('Save Location', false);
        $response->assertSee('Use my current location', false);
        $response->assertSee('unpkg.com/leaflet@1.9.4', false);
        $response->assertSee('tile.openstreetmap.org', false);
    }

    public function test_pharmacy_location_store_saves_coords_and_shows_confirmed_state(): void
    {
        $this->post(route('register.pharmacy.account'), [
            'name' => 'Pharma Owner',
            'email' => 'owner@example.com',
            'password' => 'password',
            'password_confirmation' => 'password',
        ])->assertRedirect(route('register.pharmacy.details'));

        // Save a location from the map page.
        $this->post(route('register.pharmacy.location.store'), [
            'latitude' => 14.5995,
            'longitude' => 120.9842,
            'address' => 'Manila, Philippines',
        ])->assertRedirect(route('register.pharmacy.details'));

        // The details form now reflects the confirmed state and hidden inputs.
        $response = $this->get(route('register.pharmacy.details'));
        $response->assertStatus(200);
        $response->assertSee('Location confirmed', false);
        $response->assertSee('value="14.5995"', false);
        $response->assertSee('value="120.9842"', false);
    }

    public function test_pharmacy_location_store_rejects_out_of_range_coords(): void
    {
        $this->post(route('register.pharmacy.account'), [
            'name' => 'Pharma Owner',
            'email' => 'owner@example.com',
            'password' => 'password',
            'password_confirmation' => 'password',
        ])->assertRedirect(route('register.pharmacy.details'));

        $response = $this->post(route('register.pharmacy.location.store'), [
            'latitude' => 999,
            'longitude' => 999,
        ]);

        $response->assertSessionHasErrors(['latitude', 'longitude']);
    }

    public function test_pharmacy_location_store_redirects_without_step_one_session(): void
    {
        $response = $this->post(route('register.pharmacy.location.store'), [
            'latitude' => 14.5995,
            'longitude' => 120.9842,
        ]);

        $response->assertRedirect(route('register.pharmacy'));
    }

    public function test_pharmacy_step_two_store_redirects_to_step_one_without_session(): void
    {
        $response = $this->post(route('register.pharmacy.store'), [
            'pharmacy_name' => 'City Pharmacy',
            'pharmacyAddress' => '123 Main St',
        ]);

        $response->assertRedirect(route('register.pharmacy'));
        $this->assertGuest();
        $this->assertDatabaseCount('users', 0);
    }

    public function test_pharmacy_full_happy_path_creates_pending_pharmacy_and_redirects(): void
    {
        // Step 1
        $this->post(route('register.pharmacy.account'), [
            'name' => 'Pharma Owner',
            'email' => 'owner@example.com',
            'password' => 'password',
            'password_confirmation' => 'password',
        ])->assertRedirect(route('register.pharmacy.details'));

        // Step 2
        $response = $this->post(route('register.pharmacy.store'), [
            'pharmacy_name' => 'City Pharmacy',
            'pharmacyAddress' => '123 Main St',
            'contactNumber' => '0917-123-4567',
            'latitude' => 14.5995,
            'longitude' => 120.9842,
        ]);

        $response->assertRedirect(route('pharmacy.requirements'));
        $this->assertAuthenticated();

        $user = User::where('email', 'owner@example.com')->first();
        $this->assertNotNull($user);
        $this->assertSame('pharmacy', $user->role);
        $this->assertTrue(Hash::check('password', $user->password));

        $pharmacy = Pharmacy::where('user_id', $user->id)->first();
        $this->assertNotNull($pharmacy);
        $this->assertSame('City Pharmacy', $pharmacy->pharmacy_name);
        $this->assertSame('123 Main St', $pharmacy->pharmacyAddress);
        $this->assertSame('pending', $pharmacy->status);
    }
}
