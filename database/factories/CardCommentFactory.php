<?php

namespace Database\Factories;

use App\Models\CardComment;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * Pass the tenant and card explicitly: `CardComment::factory()->for($tenant)->for($card)`.
 *
 * @extends Factory<CardComment>
 */
class CardCommentFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'user_id' => User::factory(),
            'body' => fake()->sentence(),
        ];
    }
}
