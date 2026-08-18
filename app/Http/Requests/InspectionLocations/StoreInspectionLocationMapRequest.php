<?php

declare(strict_types=1);

namespace App\Http\Requests\InspectionLocations;

use App\Models\DefectCategory;
use App\Models\Inspection;
use App\Models\InspectionLocationMap;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

final class StoreInspectionLocationMapRequest extends FormRequest
{
    public function authorize(): bool
    {
        $inspection = $this->route('inspection');
        $category = DefectCategory::query()->find($this->input('defect_category_id'));

        return $inspection instanceof Inspection
            && $category instanceof DefectCategory
            && ($this->user()?->can('create', [InspectionLocationMap::class, $inspection, $category]) ?? false);
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
            'defect_category_id' => ['required', 'integer', Rule::exists('defect_categories', 'id')->where(fn ($query) => $query->where('organization_id', $this->user()?->organization_id)->where('status', 'active'))],
            'position' => ['nullable', 'integer', 'min:1', 'max:'.config('inspection_locations.limits.maps_per_inspection')],
        ];
    }
}
