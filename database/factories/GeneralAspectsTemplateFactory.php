<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\GeneralAspectsTemplate;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<GeneralAspectsTemplate> */
final class GeneralAspectsTemplateFactory extends Factory
{
    protected $model = GeneralAspectsTemplate::class;

    public function definition(): array
    {
        return [
            'organization_id' => null,
            'name' => fake()->sentence(3),
            'schema_version' => 1,
            'document' => [
                'type' => 'doc',
                'content' => [[
                    'type' => 'paragraph',
                    'content' => [['type' => 'text', 'text' => fake()->paragraph()]],
                ]],
            ],
            'created_by' => null,
            'updated_by' => null,
        ];
    }
}
