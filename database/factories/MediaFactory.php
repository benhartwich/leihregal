<?php

namespace Database\Factories;

use App\Enums\MediaStatus;
use App\Enums\MediaType;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\Media>
 */
class MediaFactory extends Factory
{
    public function definition(): array
    {
        return [
            'type'          => MediaType::Buch,
            'title'         => fake()->sentence(3),
            'author'        => fake()->name(),
            'publisher'     => fake()->company(),
            'year'          => fake()->numberBetween(1990, 2026),
            'language'      => 'de',
            'status'        => MediaStatus::Verfuegbar,
            'internal_code' => 'LIB-' . fake()->unique()->numberBetween(100000, 999999),
            // Pflichtspalte ohne DB-Default (siehe create_media_tables).
            'created_by'    => User::factory()->kurator(),
        ];
    }

    public function ausgemustert(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => MediaStatus::Ausgemustert,
        ]);
    }

    public function verloren(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => MediaStatus::Verloren,
        ]);
    }
}
