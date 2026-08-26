<?php

namespace Database\Factories;

use App\Enums\UserRole;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\User>
 */
class UserFactory extends Factory
{
    /**
     * The current password being used by the factory.
     */
    protected static ?string $password;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'name' => fake()->name(),
            'email' => fake()->unique()->safeEmail(),
            'email_verified_at' => now(),
            'password' => static::$password ??= Hash::make('password'),
            'remember_token' => Str::random(10),
            // Müssen gesetzt sein, auch wenn die Spalten DB-Defaults haben:
            // actingAs() nutzt die Model-Instanz aus create(), und die kennt
            // per DB-Default gefüllte Spalten nicht. Ohne 'active' liest die
            // RequireActive-Middleware null und loggt den Nutzer sofort aus.
            'role' => UserRole::Betreuer,
            'active' => true,
        ];
    }

    /**
     * Indicate that the model's email address should be unverified.
     */
    public function unverified(): static
    {
        return $this->state(fn (array $attributes) => [
            'email_verified_at' => null,
        ]);
    }

    public function kurator(): static
    {
        return $this->state(fn (array $attributes) => [
            'role' => UserRole::Kurator,
        ]);
    }

    public function admin(): static
    {
        return $this->state(fn (array $attributes) => [
            'role' => UserRole::Admin,
        ]);
    }

    /**
     * Deaktiviertes Konto – darf sich nicht anmelden.
     */
    public function deaktiviert(): static
    {
        return $this->state(fn (array $attributes) => [
            'active' => false,
        ]);
    }
}
