<?php

declare(strict_types=1);

namespace App\Http\Requests\Classification;

use App\Models\DefectCategory;
use App\Support\TextNormalizer;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

final class StoreDefectCategoryRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('create', DefectCategory::class) ?? false;
    }

    protected function prepareForValidation(): void
    {
        $prepared = [
            'name' => TextNormalizer::text((string) $this->input('name')),
            'code' => TextNormalizer::technicalCode((string) $this->input('code')),
            'description' => TextNormalizer::nullableText($this->input('description')),
        ];

        if ($this->has('requires_location_map')) {
            $prepared['requires_location_map'] = $this->boolean('requires_location_map');
        }

        if ($this->has('confirm_location_map_requirement')) {
            $prepared['confirm_location_map_requirement'] = $this->boolean('confirm_location_map_requirement');
        }

        $this->merge($prepared);
    }

    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:120'],
            'code' => [
                'required', 'string', 'max:30', 'regex:/^[A-Z0-9]+$/',
                Rule::unique('defect_categories', 'code')->where('organization_id', $this->user()?->organization_id),
            ],
            'description' => ['nullable', 'string', 'max:5000'],
            'position' => ['nullable', 'integer', 'min:1'],
            'status' => ['nullable', Rule::in(['active', 'inactive'])],
            'requires_location_map' => ['sometimes', 'boolean'],
            'confirm_location_map_requirement' => $this->boolean('requires_location_map')
                ? ['required', 'accepted']
                : ['nullable'],
        ];
    }
}
