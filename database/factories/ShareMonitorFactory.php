<?php

namespace Database\Factories;

use App\Models\Monitor;
use App\Models\ShareMonitor;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<ShareMonitor>
 */
class ShareMonitorFactory extends Factory
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
