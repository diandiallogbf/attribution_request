<?php

namespace Database\Factories;

use App\Enums\DeviceStatus;
use App\Models\Device;
use App\Models\DeviceModel;
use App\Support\Luhn;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Device>
 */
class DeviceFactory extends Factory
{
    public function definition(): array
    {
        return [
            'device_model_id' => DeviceModel::factory(),
            'imei' => Luhn::complete('35'.fake()->unique()->numerify('############')),
            'serial_number' => fake()->unique()->bothify('R5C?#######'),
            'status' => DeviceStatus::InStock,
            'acquired_at' => fake()->dateTimeBetween('-3 years', '-1 month'),
        ];
    }

    public function assigned(): static
    {
        return $this->state(fn () => ['status' => DeviceStatus::Assigned]);
    }
}
