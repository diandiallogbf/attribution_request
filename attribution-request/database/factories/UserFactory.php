<?php

namespace Database\Factories;

use App\Enums\EmployeeCategory;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

/**
 * @extends Factory<User>
 */
class UserFactory extends Factory
{
    protected static ?string $password;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $firstName = fake('fr_FR')->firstName();
        $lastName = fake('fr_FR')->lastName();

        return [
            'matricule' => fake()->unique()->numerify('####'),
            'first_name' => $firstName,
            'last_name' => $lastName,
            // « Dos Santos », « N'Diaye »… : on ne garde que lettres, chiffres et points pour une adresse valide.
            'email' => preg_replace('/[^a-z0-9.]/', '', Str::lower(Str::ascii($firstName.'.'.$lastName))).fake()->unique()->numberBetween(1, 9999).'@agl.test',
            'email_verified_at' => now(),
            'password' => static::$password ??= Hash::make('password'),
            'job_title' => fake('fr_FR')->jobTitle(),
            'category' => EmployeeCategory::Employee,
            // Explicitement nuls : en mode strict, un attribut absent du modèle créé lève une exception.
            'department_id' => null,
            'site_id' => null,
            'manager_id' => null,
            'is_active' => true,
            'remember_token' => Str::random(10),
            'two_factor_secret' => null,
            'two_factor_recovery_codes' => null,
            'two_factor_confirmed_at' => null,
        ];
    }

    public function category(EmployeeCategory $category): static
    {
        return $this->state(fn () => ['category' => $category]);
    }

    public function managedBy(User $manager): static
    {
        return $this->state(fn () => [
            'manager_id' => $manager->id,
            'department_id' => $manager->department_id,
            'site_id' => $manager->site_id,
        ]);
    }

    public function inactive(): static
    {
        return $this->state(fn () => ['is_active' => false]);
    }

    public function unverified(): static
    {
        return $this->state(fn () => ['email_verified_at' => null]);
    }

    public function withTwoFactor(): static
    {
        return $this->state(fn () => [
            'two_factor_secret' => encrypt('secret'),
            'two_factor_recovery_codes' => encrypt(json_encode(['recovery-code-1'])),
            'two_factor_confirmed_at' => now(),
        ]);
    }
}
