<?php

namespace Database\Factories;

use App\Models\BaseMonitor;
use App\Models\Monitor;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<BaseMonitor>
 */
class BaseMonitorFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'monitor_id' => Monitor::factory(),
        ];
    }

    public function forMonitor(Monitor $monitor): static
    {
        return $this->state(fn() => ['monitor_id' => $monitor->id]);
    }
}
