<?php

namespace Database\Factories;

use App\Models\Pharmacy;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Pharmacy>
 */
class PharmacyFactory extends Factory
{
    protected $model = Pharmacy::class;

    public function definition(): array
    {
        return [
            'pharmacy_name'   => fake()->company() . ' Pharmacy',
            'pharmacyAddress' => fake()->streetAddress() . ', Legazpi City',
            'latitude'        => fake()->randomFloat(7, 13.12, 13.18),
            'longitude'       => fake()->randomFloat(7, 123.72, 123.78),
            'contactNumber'   => fake()->phoneNumber(),
            'status'          => 'approved',
            'user_id'         => null,
        ];
    }

    /** Approved pharmacy (default). */
    public function approved(): static
    {
        return $this->state(['status' => 'approved']);
    }

    /** Pending pharmacy. */
    public function pending(): static
    {
        return $this->state(['status' => 'pending']);
    }

    /** Attach an owner user. */
    public function withOwner(User $user): static
    {
        return $this->state(['user_id' => $user->id]);
    }
}
