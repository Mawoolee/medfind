<?php

namespace Tests\Feature;

use App\Http\Controllers\PharmacyRequirementsController;
use App\Models\Pharmacy;
use App\Models\User;
use App\Notifications\PharmacyStatusNotification;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class PharmacyRequirementsTest extends TestCase
{
    use RefreshDatabase;

    public function test_fda_certificate_is_required_and_philhealth_remains_optional_in_pharmacy_workflow(): void
    {
        $user = User::factory()->create(['role' => 'pharmacy']);
        Pharmacy::factory()->pending()->withOwner($user)->create();

        $this->assertTrue(PharmacyRequirementsController::DOCS['fda']['required']);
        $this->assertFalse(PharmacyRequirementsController::DOCS['philhealth']['required']);

        $response = $this->actingAs($user)->get(route('pharmacy.requirements'));

        $response->assertOk()->assertSee('FDA Certificate');

        $html = $response->getContent();
        $fdaCardStart = strpos($html, 'title="FDA Certificate"');
        $fdaInputStart = strpos($html, 'id="file_fda"', $fdaCardStart);

        $this->assertNotFalse($fdaCardStart);
        $this->assertNotFalse($fdaInputStart);

        $fdaCardHeader = substr($html, $fdaCardStart, $fdaInputStart - $fdaCardStart);
        $this->assertStringContainsString('Required', $fdaCardHeader);
        $this->assertStringNotContainsString('Optional', $fdaCardHeader);
        $this->assertStringNotContainsString('(optional)', $fdaCardHeader);
    }

    public function test_admin_requirements_review_labels_fda_without_optional_marker(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        Pharmacy::factory()->pending()->create([
            'requirements' => $this->requiredDocuments(),
        ]);

        $this->actingAs($admin)
            ->get(route('admin.requirements'))
            ->assertOk()
            ->assertSee('FDA Certificate')
            ->assertDontSee('FDA Certificate (optional)');
    }

    public function test_admin_cannot_approve_requirements_without_fda_certificate(): void
    {
        Notification::fake();

        $admin = User::factory()->create(['role' => 'admin']);
        $owner = User::factory()->create(['role' => 'pharmacy']);
        $pharmacy = Pharmacy::factory()->pending()->withOwner($owner)->create([
            'requirements' => [
                'bir' => 'requirements/bir.pdf',
                'business' => 'requirements/business.pdf',
                'pharmacist' => 'requirements/pharmacist.pdf',
            ],
        ]);

        $this->actingAs($admin)
            ->post(route('admin.requirements.approve', $pharmacy))
            ->assertRedirect(route('admin.requirements'))
            ->assertSessionHas('error', fn (string $message): bool =>
                str_contains($message, 'Missing required documents')
                && str_contains($message, 'FDA Certificate')
            );

        $this->assertSame('pending', $pharmacy->fresh()->status);
        Notification::assertNothingSent();
        $this->assertDatabaseMissing('activity_logs', [
            'action' => 'approved',
            'entity_type' => 'Pharmacy',
            'entity_id' => $pharmacy->id,
        ]);
    }

    public function test_admin_can_approve_with_all_required_documents_and_without_philhealth(): void
    {
        Notification::fake();

        $admin = User::factory()->create(['role' => 'admin']);
        $owner = User::factory()->create(['role' => 'pharmacy']);
        $requirements = $this->requiredDocuments();
        $pharmacy = Pharmacy::factory()->pending()->withOwner($owner)->create([
            'requirements' => $requirements,
        ]);

        $this->assertArrayNotHasKey('philhealth', $requirements);

        $this->actingAs($admin)
            ->post(route('admin.requirements.approve', $pharmacy))
            ->assertRedirect(route('admin.requirements'))
            ->assertSessionHas('success');

        $this->assertSame('approved', $pharmacy->fresh()->status);
        Notification::assertSentTo(
            $owner,
            PharmacyStatusNotification::class,
            fn (PharmacyStatusNotification $notification): bool => $notification->status === 'approved'
        );
        $this->assertDatabaseHas('activity_logs', [
            'user_id' => $admin->id,
            'action' => 'approved',
            'entity_type' => 'Pharmacy',
            'entity_id' => $pharmacy->id,
        ]);
    }

    public function test_incremental_upload_adds_fda_without_requiring_or_removing_existing_documents(): void
    {
        Storage::fake('local');

        $user = User::factory()->create(['role' => 'pharmacy']);
        $existingRequirements = [
            'bir' => 'requirements/bir.pdf',
            'business' => 'requirements/business.pdf',
            'pharmacist' => 'requirements/pharmacist.pdf',
        ];
        $pharmacy = Pharmacy::factory()->pending()->withOwner($user)->create([
            'requirements' => $existingRequirements,
        ]);

        $this->actingAs($user)
            ->post(route('pharmacy.requirements.store'), [
                'doc_fda' => UploadedFile::fake()->create('fda-certificate.pdf', 100, 'application/pdf'),
            ])
            ->assertRedirect(route('pharmacy.requirements'))
            ->assertSessionHas('success');

        $requirements = $pharmacy->fresh()->requirements;

        foreach ($existingRequirements as $key => $path) {
            $this->assertSame($path, $requirements[$key]);
        }

        $this->assertArrayHasKey('fda', $requirements);
        $this->assertArrayNotHasKey('philhealth', $requirements);
        Storage::disk('local')->assertExists($requirements['fda']);
    }

    private function requiredDocuments(): array
    {
        return [
            'bir' => 'requirements/bir.pdf',
            'business' => 'requirements/business.pdf',
            'fda' => 'requirements/fda.pdf',
            'pharmacist' => 'requirements/pharmacist.pdf',
        ];
    }
}
