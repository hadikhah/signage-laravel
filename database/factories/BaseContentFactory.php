<?php

namespace Database\Factories;

use App\Models\BaseContent;
use App\Models\Content;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<BaseContent>
 */
class BaseContentFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'content_id' => Content::factory(),
        ];
    }

    public function forContent(Content $content): static
    {
        return $this->state(fn() => ['content_id' => $content->id]);
    }
}
