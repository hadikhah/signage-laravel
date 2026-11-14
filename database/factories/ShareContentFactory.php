<?php

namespace Database\Factories;

use App\Models\Content;
use App\Models\ShareContent;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<ShareContent>
 */
class ShareContentFactory extends Factory
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

    public function forContents(Content $content)
    {
        return $this->state(fn () => ['content_id' => $content->id]);
    }
}
