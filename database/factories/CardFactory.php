<?php

namespace Database\Factories;

use App\Models\Card;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * Pass the tenant, board and column explicitly:
 * `Card::factory()->for($tenant)->for($board)->for($column, 'column')`.
 *
 * @extends Factory<Card>
 */
class CardFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'title' => fake()->sentence(4),
            'description' => null,
            'position' => 0,
        ];
    }
}
