<?php

namespace Database\Factories;

use App\Models\Author;
use App\Models\Genre;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\Book>
 */
class BookFactory extends Factory
{
    public function definition(): array
    {
        $title = fake()->unique()->sentence(3);

        return [
            'author_id' => Author::factory(),
            'genre_id' => Genre::factory(),
            'title' => $title,
            'slug' => Str::slug($title),
            'description' => fake()->paragraph(),
            'excerpt' => fake()->paragraph(4),
            'language' => 'en',
            'page_count' => fake()->numberBetween(120, 640),
            'price_paise' => fake()->numberBetween(4900, 89900),
            'tax_rate' => 18.00,
            'is_published' => true,
            'published_at' => now(),
            'cover_image_path' => 'covers/sample.jpg',
            'file_path' => 'books/sample.pdf',
            'file_format' => 'pdf',
        ];
    }

    /**
     * A draft — present in the admin panel, invisible in the catalogue.
     */
    public function unpublished(): static
    {
        return $this->state(fn () => [
            'is_published' => false,
            'published_at' => null,
        ]);
    }

    public function featured(): static
    {
        return $this->state(fn () => ['is_featured' => true]);
    }

    public function free(): static
    {
        return $this->state(fn () => ['price_paise' => 0]);
    }
}
