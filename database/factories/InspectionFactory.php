<?php

namespace Database\Factories;

use App\Enums\InspectionStatus;
use App\Models\Inspection;
use App\Models\Organization;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<Inspection> */
class InspectionFactory extends Factory
{
    public function definition(): array
    {
        return ['organization_id' => Organization::factory(), 'status' => InspectionStatus::Planned];
    }
}
