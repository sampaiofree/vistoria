<?php

declare(strict_types=1);

namespace App\Http\Requests\InspectionLocations;

use App\Models\InspectionLocationMap;
use Illuminate\Foundation\Http\FormRequest;

final class StoreInspectionLocationMarkerRequest extends FormRequest
{
    public function authorize(): bool
    {
        $map = $this->route('map');

        return $map instanceof InspectionLocationMap && ($this->user()?->can('update', $map) ?? false);
    }

    public function rules(): array
    {
        return [
            'defect_assessment_id' => ['required', 'integer'],
            'label' => ['nullable', 'string', 'max:240'],
            'geometry' => ['required', 'array'],
            'style' => ['nullable', 'array'],
            'position' => ['nullable', 'integer', 'min:1', 'max:'.config('inspection_locations.limits.markers_per_map')],
            'map_lock_version' => ['required', 'integer', 'min:1'],
        ];
    }
}
