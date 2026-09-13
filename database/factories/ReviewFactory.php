<?php

namespace Database\Factories;

use App\Models\Book;
use App\Models\Review;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Review>
 */
class ReviewFactory extends Factory
{
    public function definition(): array
    {
        return [
            'book_id' => Book::factory(),
            'user_id' => User::factory(),
            'rating' => fake()->numberBetween(1, 5),
            'title' => fake()->sentence(4),
            'body' => fake()->paragraph(),
            'verified' => true,
            'percent_read' => fake()->numberBetween(0, 100),
        ];
    }

    public function hidden(): static
    {
        return $this->state(fn () => ['hidden_at' => now(), 'hidden_reason' => 'Test']);
    }
}
