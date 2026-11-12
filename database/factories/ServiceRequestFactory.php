<?php

namespace Database\Factories;

use App\Enums\RequestStatus;
use App\Enums\RequestType;
use App\Models\ServiceRequest;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<ServiceRequest>
 */
class ServiceRequestFactory extends Factory
{
    public function definition(): array
    {
        $beneficiary = User::factory();

        return [
            'type' => RequestType::Telephony,
            'status' => RequestStatus::Draft,
            'requester_id' => $beneficiary,
            'beneficiary_id' => fn (array $attributes) => $attributes['requester_id'],
        ];
    }

    public function status(RequestStatus $status): static
    {
        return $this->state(fn () => ['status' => $status]);
    }

    public function forBeneficiary(User $beneficiary, ?User $requester = null): static
    {
        return $this->state(fn () => [
            'beneficiary_id' => $beneficiary->id,
            'requester_id' => ($requester ?? $beneficiary)->id,
            'site_id' => $beneficiary->site_id,
        ]);
    }
}
