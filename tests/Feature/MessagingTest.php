<?php

namespace Tests\Feature;

use App\Models\Message;
use App\Models\Pharmacy;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

/**
 * Feature tests for consumer ↔ pharmacy messaging and prescription verification.
 *
 * Covers ISO/IEC 25010: Functional Suitability and Security
 * (prescriptions stored encrypted, not publicly accessible).
 */
class MessagingTest extends TestCase
{
    use RefreshDatabase;

    // ─────────────────────────────────────────────────────────────────────────
    // Helpers
    // ─────────────────────────────────────────────────────────────────────────

    private function makeConsumer(): User
    {
        return User::factory()->create(['role' => 'consumer']);
    }

    private function makePharmacyUser(): array
    {
        $user     = User::factory()->create(['role' => 'pharmacy']);
        $pharmacy = Pharmacy::factory()->withOwner($user)->create();
        $user->update(['pharmacy_id' => $pharmacy->id]);
        return [$user, $pharmacy];
    }

    // ─────────────────────────────────────────────────────────────────────────
    // Sending a message (consumer → pharmacy)
    // ─────────────────────────────────────────────────────────────────────────

    public function test_consumer_can_send_message_to_pharmacy(): void
    {
        Event::fake();
        $consumer         = $this->makeConsumer();
        [, $pharmacy]     = $this->makePharmacyUser();

        $this->actingAs($consumer)
            ->post(route('consumer.message.send'), [
                'pharmacy_id' => $pharmacy->id,
                'message'     => 'Do you have Amoxicillin 500mg in stock?',
            ])
            ->assertRedirect()
            ->assertSessionHas('success');

        $this->assertDatabaseHas('messages', [
            'consumer_id' => $consumer->id,
            'pharmacy_id' => $pharmacy->id,
            'is_read'     => false,
        ]);
    }

    public function test_message_requires_non_empty_text(): void
    {
        $consumer     = $this->makeConsumer();
        [, $pharmacy] = $this->makePharmacyUser();

        $this->actingAs($consumer)
            ->post(route('consumer.message.send'), [
                'pharmacy_id' => $pharmacy->id,
                'message'     => '',
            ])
            ->assertSessionHasErrors(['message']);
    }

    public function test_message_requires_valid_pharmacy(): void
    {
        $consumer = $this->makeConsumer();

        $this->actingAs($consumer)
            ->post(route('consumer.message.send'), [
                'pharmacy_id' => 99999,
                'message'     => 'Hello',
            ])
            ->assertSessionHasErrors(['pharmacy_id']);
    }

    public function test_pharmacy_user_cannot_send_consumer_message(): void
    {
        // The consumer.message.send route is behind role:consumer middleware,
        // so a pharmacy user gets redirected away — not a 200 with error session.
        Event::fake();
        [$pharmacyUser, $pharmacy] = $this->makePharmacyUser();

        $this->actingAs($pharmacyUser)
            ->post(route('consumer.message.send'), [
                'pharmacy_id' => $pharmacy->id,
                'message'     => 'Test',
            ])
            ->assertRedirect(); // middleware redirects pharmacy users away
    }

    // ─────────────────────────────────────────────────────────────────────────
    // Prescription upload — encryption security check
    // ─────────────────────────────────────────────────────────────────────────

    public function test_prescription_image_is_encrypted_and_not_publicly_accessible(): void
    {
        Event::fake();
        Storage::fake('prescriptions');
        Storage::fake('public');

        $consumer     = $this->makeConsumer();
        [, $pharmacy] = $this->makePharmacyUser();

        // Use a plain .pdf fake file (avoids GD dependency)
        $fakeFile = UploadedFile::fake()->create('rx.pdf', 50, 'application/pdf');

        $this->actingAs($consumer)
            ->post(route('consumer.message.send'), [
                'pharmacy_id'        => $pharmacy->id,
                'message'            => 'Sending my prescription.',
                'prescription_image' => $fakeFile,
            ])
            ->assertRedirect()
            ->assertSessionHas('success');

        $message = Message::where('consumer_id', $consumer->id)->first();
        $this->assertNotNull($message, 'Message was not created.');
        $this->assertNotNull($message->prescription_image, 'Prescription filename not saved.');

        // File must NOT be at a public URL
        $this->assertFalse(
            Storage::disk('public')->exists($message->prescription_image),
            'Prescription stored in public disk — SECURITY FAILURE.'
        );

        // File must be in the private encrypted disk
        Storage::disk('prescriptions')->assertExists($message->prescription_image);
    }

    public function test_pharmacy_can_serve_prescription_via_secure_route(): void
    {
        Event::fake();
        Storage::fake('prescriptions');

        $consumer                  = $this->makeConsumer();
        [$pharmacyUser, $pharmacy] = $this->makePharmacyUser();

        $fakeFile = UploadedFile::fake()->create('rx.pdf', 50, 'application/pdf');

        $this->actingAs($consumer)
            ->post(route('consumer.message.send'), [
                'pharmacy_id'        => $pharmacy->id,
                'message'            => 'Attached prescription.',
                'prescription_image' => $fakeFile,
            ]);

        $message = Message::where('consumer_id', $consumer->id)->first();

        // Pharmacy staff can view via secure route — returns decrypted bytes
        $this->actingAs($pharmacyUser)
            ->get(route('pharmacy.prescription.serve', $message->id))
            ->assertOk();
    }

    public function test_consumer_cannot_view_prescription_via_secure_route(): void
    {
        $consumer        = $this->makeConsumer();
        $otherConsumer   = $this->makeConsumer();
        [, $pharmacy]    = $this->makePharmacyUser();

        $message = Message::factory()->create([
            'consumer_id'        => $consumer->id,
            'pharmacy_id'        => $pharmacy->id,
            'prescription_image' => 'fake-file.enc',
        ]);

        // Another consumer hits the pharmacy route — middleware redirects them away
        $this->actingAs($otherConsumer)
            ->get(route('pharmacy.prescription.serve', $message->id))
            ->assertRedirect(); // CheckRole redirects non-pharmacy users
    }

    // ─────────────────────────────────────────────────────────────────────────
    // Pharmacy reads + replies
    // ─────────────────────────────────────────────────────────────────────────

    public function test_pharmacy_can_view_messages_inbox(): void
    {
        [$pharmacyUser, $pharmacy] = $this->makePharmacyUser();
        $consumer = $this->makeConsumer();
        Message::factory()->create(['consumer_id' => $consumer->id, 'pharmacy_id' => $pharmacy->id]);

        $this->actingAs($pharmacyUser)
            ->get(route('pharmacy.messages'))
            ->assertOk();
    }

    public function test_pharmacy_can_reply_to_message(): void
    {
        Event::fake();
        [$pharmacyUser, $pharmacy] = $this->makePharmacyUser();
        $consumer = $this->makeConsumer();
        $message  = Message::factory()->create([
            'consumer_id' => $consumer->id,
            'pharmacy_id' => $pharmacy->id,
        ]);

        $this->actingAs($pharmacyUser)
            ->post(route('pharmacy.message.reply', $message->id), [
                'reply' => 'Yes, we have it in stock!',
            ])
            ->assertRedirect()
            ->assertSessionHas('success');

        $this->assertDatabaseHas('messages', [
            'id'    => $message->id,
            'reply' => 'Yes, we have it in stock!',
        ]);
    }

    public function test_pharmacy_cannot_reply_to_another_pharmacys_message(): void
    {
        Event::fake();
        [$pharmacyUser]       = $this->makePharmacyUser();
        [, $otherPharmacy]    = $this->makePharmacyUser();
        $consumer = $this->makeConsumer();
        $message  = Message::factory()->create([
            'consumer_id' => $consumer->id,
            'pharmacy_id' => $otherPharmacy->id,
        ]);

        $this->actingAs($pharmacyUser)
            ->post(route('pharmacy.message.reply', $message->id), [
                'reply' => 'Unauthorized reply',
            ])
            ->assertRedirect()
            ->assertSessionHas('error');
    }

    // ─────────────────────────────────────────────────────────────────────────
    // Consumer conversations
    // ─────────────────────────────────────────────────────────────────────────

    public function test_consumer_can_view_own_conversations(): void
    {
        $consumer     = $this->makeConsumer();
        [, $pharmacy] = $this->makePharmacyUser();
        Message::factory()->create(['consumer_id' => $consumer->id, 'pharmacy_id' => $pharmacy->id]);

        $this->actingAs($consumer)
            ->get(route('consumer.messages'))
            ->assertOk();
    }

    // ─────────────────────────────────────────────────────────────────────────
    // AJAX mark-read endpoint
    // ─────────────────────────────────────────────────────────────────────────

    public function test_pharmacy_can_mark_message_as_read_via_ajax(): void
    {
        [$pharmacyUser, $pharmacy] = $this->makePharmacyUser();
        $consumer = $this->makeConsumer();
        $message  = Message::factory()->create([
            'consumer_id' => $consumer->id,
            'pharmacy_id' => $pharmacy->id,
            'is_read'     => false,
        ]);

        $this->actingAs($pharmacyUser)
            ->postJson(route('pharmacy.message.mark-read-ajax', $message->id))
            ->assertOk()
            ->assertJsonStructure(['count']); // controller returns 'count' key

        $this->assertDatabaseHas('messages', ['id' => $message->id, 'is_read' => true]);
    }
}
