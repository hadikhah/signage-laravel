<?php

namespace Database\Factories;

use App\Models\Content;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Content>
 */
class ContentFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'content_name' => fake()->words(2, true),
            'client_file_name' => fake()->optional(0.8)->slug(2) . '.' . fake()->randomElement(['jpg', 'png', 'mp4']),
            'user_id' => User::factory(),
            // 1=image, 2=video, 3=text, 4=flash, 5=url
            'content_type' => fake()->numberBetween(1, 5),
            'is_auto_login' => fake()->boolean(20),
            'deleted' => false,
            'order' => fake()->numberBetween(0, 100),
            'uploaded_at' => fake()->optional(0.9)->dateTimeThisYear(),
            'from_date' => fake()->optional(0.7)->dateTimeBetween('-1 month', 'now'),
            'to_date' => fake()->optional(0.7)->dateTimeBetween('now', '+3 months'),
            'content_url_user_password_id' => null,
        ];

    }
}
