<?php

namespace Database\Factories;

use App\Models\City;
use App\Models\Department;
use App\Models\Office;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Office>
 */
class OfficeFactory extends Factory
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
            // Must mirror parent_id. Default state = top-level.
            'is_child' => false,
            'lock' => null,
            'office_id' => Office::factory(),
            // No parent by default — see childOf() below.
            'parent_id' => null,

        ];


    }

    /** Attach as a child of an existing department. Sets is_child=true. */
    public function childOf(Department $parent): static
    {
        return $this->state(fn() => [
            'parent_id' => $parent->id,
            'office_id' => $parent->office_id,
            'is_child' => true,
        ]);
    }

    /** Explicit top-level — clears parent_id and is_child. */
    public function topLevel(): static
    {
        return $this->state(fn() => [
            'parent_id' => null,
            'is_child' => false,
        ]);
    }

    /** Reuse an existing office. */
    public function inOffice(Office $office): static
    {
        return $this->state(fn() => ['office_id' => $office->id]);
    }

}
