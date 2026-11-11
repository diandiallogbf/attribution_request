<?php

namespace Database\Factories;

use App\Enums\PhoneType;
use App\Models\DeviceModel;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<DeviceModel>
 */
class DeviceModelFactory extends Factory
{
    public function definition(): array
    {
        return [
            'brand' => 'Samsung',
            'name' => 'Galaxy '.fake()->unique()->bothify('A##'),
            'phone_type' => PhoneType::SamsungAndroid,
            'storage_gb' => 128,
            'unit_price_gnf' => 2_000_000,
            'is_active' => true,
        ];
    }
}
