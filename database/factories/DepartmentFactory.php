<?php

namespace Database\Factories;

use App\Models\Department;
use App\Models\Office;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Department>
 */
class DepartmentFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'department_name' => fake()->words(2, true),
            'daily_message' => fake()->optional(0.7)->sentence(),
            'lock' => null,
            'office_id' => Office::factory(),
            'parent_id' => null,
        ];

    }

    /** Attach as a child of an existing department. */
    public function childOf(Department $parent): static
    {
        return $this->state(fn () => [
            'parent_id' => $parent->id,
            'office_id' => $parent->office_id,
        ]);
    }

    /** Reuse an existing office. */
    public function inOffice(Office $office): static
    {
        return $this->state(fn () => ['office_id' => $office->id]);
    }
}
