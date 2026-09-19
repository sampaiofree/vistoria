<?php

declare(strict_types=1);

namespace App\Http\Requests\InspectionLocations;

use App\Enums\DefectCategory;
use App\Models\Inspection;
use App\Models\InspectionLocationMap;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

final class StoreInspectionLocationMapRequest extends FormRequest
{
    public function authorize(): bool
    {
        $inspection = $this->route('inspection');

        return $inspection instanceof Inspection
            && ($this->user()?->can('create', [InspectionLocationMap::class, $inspection]) ?? false);
    }

    protected function prepareForValidation(): void
    {
        $this->merge(['title' => is_string($this->input('title')) ? trim($this->input('title')) : $this->input('title')]);
    }

    public function rules(): array
    {
        return [
            'title' => ['required', 'string', 'max:200'],
            'description' => ['nullable', 'string', 'max:10000'],
            'category' => ['required', Rule::enum(DefectCategory::class)],
            'position' => ['nullable', 'integer', 'min:1', 'max:'.config('inspection_locations.limits.maps_per_inspection')],
        ];
    }
}
