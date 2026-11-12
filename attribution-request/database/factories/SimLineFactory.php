<?php

namespace Database\Factories;

use App\Enums\LineOption;
use App\Enums\PlanType;
use App\Enums\SimLineStatus;
use App\Models\Operator;
use App\Models\SimLine;
use App\Support\Luhn;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<SimLine>
 */
class SimLineFactory extends Factory
{
    public function definition(): array
    {
        return [
            'operator_id' => fn () => Operator::firstOrCreate(['code' => 'OGN'], ['name' => 'Orange Guinée'])->id,
            'msisdn' => '+2246'.fake()->unique()->numerify('2#######'),
            'iccid' => Luhn::complete('8922401'.fake()->unique()->numerify('###########')),
            'plan_type' => PlanType::Prepaid,
            'options' => [LineOption::NationalCalls],
            'monthly_recharge_gnf' => 100_000,
            'is_fleet' => true,
            'status' => SimLineStatus::Active,
            'activated_at' => fake()->dateTimeBetween('-3 years', '-1 month'),
        ];
    }
}
