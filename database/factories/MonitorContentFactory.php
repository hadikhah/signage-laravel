<?php

namespace Database\Factories;

use App\Models\Content;
use App\Models\Monitor;
use App\Models\MonitorContent;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<MonitorContent>
 */
class MonitorContentFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            "monitor_id" => Monitor::factory(),
            'content_id'    => Content::factory(),
            "content_order" => fake()->numberBetween(0, 20),
            "display_time" => fake()->optional(0.8)->numberBetween(5, 300),
            "active" => false
        ];
    }

    public function active(): static
    {
        return $this->state(fn() => ['active' => true]);
    }


    public function forMonitorAndContent(Monitor $monitor, Content $content, int $order = 1, int $display = 30)
    {

        return $this->state(fn() => [
            'monitor_id' => $monitor->id,
            "content_id" => $content->id,
            "content_order" => $order,
            "display_time" => $display,
            "active" => true
        ]);
    }
}
