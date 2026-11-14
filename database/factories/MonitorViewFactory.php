<?php

namespace Database\Factories;

use App\Models\Monitor;
use App\Models\MonitorView;
use App\Models\View;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<MonitorView>
 */
class MonitorViewFactory extends Factory
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
            'view_id' => View::factory(),
        ];
    }

    public function forMonitorAndView(Monitor $monitor, View $view): static
    {
        return $this->state(fn () => [
            'monitor_id' => $monitor->id,
            'view_id' => $view->id,
        ]);
    }
}
